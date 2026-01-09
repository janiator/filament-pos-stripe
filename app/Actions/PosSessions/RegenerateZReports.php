<?php

namespace App\Actions\PosSessions;

use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\ConnectedCharge;
use App\Models\PosEvent;
use App\Models\PosSession;
use App\Models\Receipt;
use Illuminate\Support\Facades\Log;

class RegenerateZReports
{
    /**
     * Regenerate Z-reports for closed sessions and attempt to find missing data
     *
     * @param array $options Options for regeneration:
     *   - 'store_id' (int|null): Filter by store ID, null for all stores
     *   - 'from_date' (string|null): Only regenerate reports for sessions closed after this date
     *   - 'to_date' (string|null): Only regenerate reports for sessions closed before this date
     *   - 'limit' (int|null): Maximum number of sessions to process
     *   - 'find_missing_data' (bool): Whether to attempt to find missing charges/receipts/events (default: true)
     *   - 'dry_run' (bool): If true, don't save changes, just report what would be done (default: false)
     * @return array Statistics about the regeneration process
     */
    public function __invoke(array $options = []): array
    {
        $storeId = $options['store_id'] ?? null;
        $fromDate = $options['from_date'] ?? null;
        $toDate = $options['to_date'] ?? null;
        $limit = $options['limit'] ?? null;
        $findMissingData = $options['find_missing_data'] ?? true;
        $dryRun = $options['dry_run'] ?? false;

        $stats = [
            'total_sessions' => 0,
            'processed' => 0,
            'regenerated' => 0,
            'charges_found' => 0,
            'receipts_found' => 0,
            'events_found' => 0,
            'errors' => [],
        ];

        // Query closed sessions
        $query = PosSession::where('status', 'closed')
            ->with(['store', 'posDevice', 'user']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($fromDate) {
            $query->whereDate('closed_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('closed_at', '<=', $toDate);
        }

        // Order by closed_at ascending to process earliest sessions first
        $query->orderBy('closed_at', 'asc');

        if ($limit) {
            $query->limit($limit);
        }

        $sessions = $query->get();
        $stats['total_sessions'] = $sessions->count();

        foreach ($sessions as $session) {
            try {
                $stats['processed']++;

                // Attempt to find missing data if enabled
                if ($findMissingData) {
                    $foundData = $this->findMissingData($session, $dryRun);
                    $stats['charges_found'] += $foundData['charges_found'];
                    $stats['receipts_found'] += $foundData['receipts_found'];
                    $stats['events_found'] += $foundData['events_found'];
                }

                // Refresh session to get any newly linked data
                $session->refresh();
                $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);

                // Update session totals if we found new charges
                if (!$dryRun) {
                    // Preserve actual_cash from original report BEFORE clearing cached data
                    $closingData = $session->closing_data ?? [];
                    $originalReport = $closingData['z_report_data'] ?? null;
                    
                    // Store current actual_cash from database as fallback
                    $databaseActualCash = $session->actual_cash;
                    
                    // If original report has actual_cash, preserve it (it's the historical snapshot)
                    // This takes precedence over database value to preserve the original closing snapshot
                    if ($originalReport && isset($originalReport['actual_cash']) && $originalReport['actual_cash'] !== null) {
                        // Report stores actual_cash in NOK (divided by 100), so convert back to øre
                        $session->actual_cash = (int) round($originalReport['actual_cash'] * 100);
                    } elseif ($databaseActualCash !== null) {
                        // If report doesn't have it but database does, keep the database value
                        $session->actual_cash = $databaseActualCash;
                    }
                    
                    $succeededCharges = $session->charges()->where('status', 'succeeded')->get();
                    $session->transaction_count = $succeededCharges->count();
                    $session->total_amount = $succeededCharges->sum('amount');
                    $session->expected_cash = $session->calculateExpectedCash();
                    // Recalculate cash_difference if actual_cash is set
                    if ($session->actual_cash !== null) {
                        $session->cash_difference = $session->actual_cash - $session->expected_cash;
                    }
                    
                    // Clear cached Z-report data to force regeneration (but preserve actual_cash)
                    unset($closingData['z_report_data']);
                    $session->closing_data = $closingData;
                    $session->saveQuietly();
                }

                // Regenerate Z-report (will generate fresh data since we cleared the cache)
                $report = PosSessionsTable::generateZReport($session);

                // Store the regenerated report in closing_data
                if (!$dryRun) {
                    $closingData = $session->closing_data ?? [];
                    $closingData['z_report_data'] = $report;
                    $closingData['z_report_regenerated_at'] = now()->toISOString();
                    $closingData['z_report_regeneration_reason'] = 'Manual regeneration with missing data recovery';
                    $session->closing_data = $closingData;
                    $session->saveQuietly(); // Save without triggering observers
                }

                $stats['regenerated']++;

            } catch (\Exception $e) {
                $errorMsg = "Session {$session->id} ({$session->session_number}): {$e->getMessage()}";
                $stats['errors'][] = $errorMsg;
                Log::error('Error regenerating Z-report', [
                    'session_id' => $session->id,
                    'session_number' => $session->session_number,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Regenerate Z-report for a single session
     *
     * @param PosSession $session
     * @param bool $findMissingData Whether to attempt to find missing data
     * @return array Statistics about the regeneration
     */
    public function regenerateSingle(PosSession $session, bool $findMissingData = true): array
    {
        $stats = [
            'charges_found' => 0,
            'receipts_found' => 0,
            'events_found' => 0,
            'success' => false,
            'error' => null,
        ];

        try {
            // Attempt to find missing data if enabled
            if ($findMissingData) {
                $foundData = $this->findMissingData($session, false);
                $stats['charges_found'] = $foundData['charges_found'];
                $stats['receipts_found'] = $foundData['receipts_found'];
                $stats['events_found'] = $foundData['events_found'];
            }

            // Refresh session to get any newly linked data
            $session->refresh();
            $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);

            // Preserve actual_cash from original report BEFORE clearing cached data
            // This ensures we don't lose the actual_cash value if it's only stored in the report
            $closingData = $session->closing_data ?? [];
            $originalReport = $closingData['z_report_data'] ?? null;
            $originalGeneratedAt = $closingData['z_report_generated_at'] ?? $closingData['z_report_regenerated_at'] ?? null;
            
            // Store current actual_cash from database as fallback
            $databaseActualCash = $session->actual_cash;
            
            // If original report has actual_cash, preserve it (it's the historical snapshot)
            // This takes precedence over database value to preserve the original closing snapshot
            if ($originalReport && isset($originalReport['actual_cash']) && $originalReport['actual_cash'] !== null) {
                // Report stores actual_cash in NOK (divided by 100), so convert back to øre
                $session->actual_cash = (int) round($originalReport['actual_cash'] * 100);
            } elseif ($databaseActualCash !== null) {
                // If report doesn't have it but database does, keep the database value
                $session->actual_cash = $databaseActualCash;
            }

            // Update session totals if we found new charges
            $succeededCharges = $session->charges()->where('status', 'succeeded')->get();
            $session->transaction_count = $succeededCharges->count();
            $session->total_amount = $succeededCharges->sum('amount');
            $session->expected_cash = $session->calculateExpectedCash();
            // Recalculate cash_difference if actual_cash is set
            if ($session->actual_cash !== null) {
                $session->cash_difference = $session->actual_cash - $session->expected_cash;
            }
            
            // Clear cached Z-report data to force regeneration (but preserve actual_cash)
            unset($closingData['z_report_data']);
            $session->closing_data = $closingData;
            // Save session with preserved actual_cash before generating report
            $session->saveQuietly();

            // Regenerate Z-report (will generate fresh data since we cleared the cache)
            $report = PosSessionsTable::generateZReport($session);

            // Store the regenerated report in closing_data
            // Also preserve the original report if it exists for comparison
            $closingData = $session->closing_data ?? [];
            if ($originalReport !== null && !isset($closingData['z_report_data_original'])) {
                $closingData['z_report_data_original'] = $originalReport;
                $closingData['z_report_original_generated_at'] = $originalGeneratedAt ?? now()->toISOString();
            }
            $closingData['z_report_data'] = $report;
            $closingData['z_report_regenerated_at'] = now()->toISOString();
            $closingData['z_report_regeneration_reason'] = 'Manual regeneration with missing data recovery';
            $closingData['z_report_regeneration_changes'] = [
                'charges_found' => $stats['charges_found'],
                'receipts_found' => $stats['receipts_found'],
                'events_found' => $stats['events_found'],
                'transaction_count_before' => isset($closingData['z_report_data_original']) ? ($closingData['z_report_data_original']['transactions_count'] ?? null) : null,
                'transaction_count_after' => $report['transactions_count'],
                'total_amount_before' => isset($closingData['z_report_data_original']) ? ($closingData['z_report_data_original']['total_amount'] ?? null) : null,
                'total_amount_after' => $report['total_amount'],
            ];
            $session->closing_data = $closingData;
            $session->saveQuietly(); // Save without triggering observers

            $stats['success'] = true;

        } catch (\Exception $e) {
            $stats['error'] = $e->getMessage();
            Log::error('Error regenerating Z-report for single session', [
                'session_id' => $session->id,
                'session_number' => $session->session_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $stats;
    }

    /**
     * Attempt to find missing charges, receipts, and events for a session
     *
     * @param PosSession $session
     * @param bool $dryRun
     * @return array Statistics about found data
     */
    protected function findMissingData(PosSession $session, bool $dryRun): array
    {
        $stats = [
            'charges_found' => 0,
            'receipts_found' => 0,
            'events_found' => 0,
        ];

        if (!$session->store || !$session->store->stripe_account_id) {
            return $stats;
        }

        $store = $session->store;
        $stripeAccountId = $store->stripe_account_id;

        // Find charges that might belong to this session but aren't linked
        // Use the session's open/close time window
        $sessionStart = $session->opened_at;
        $sessionEnd = $session->closed_at ?? now();

        // Track charge IDs we've already counted (to prevent double-counting in dry-run mode)
        // In dry-run mode, charges aren't saved, so we need to track them manually
        $countedChargeIds = [];

        // Method 1: Find charges by session_number in metadata or via receipts/events
        // Session number is unique and stored in receipt_data and potentially in charge metadata
        $sessionNumber = $session->session_number;
        
        // Get charge IDs from events that have this session number
        // Use whereRaw for PostgreSQL JSON text comparison
        $chargeIdsFromEvents = PosEvent::where('store_id', $store->id)
            ->whereNotNull('related_charge_id')
            ->whereRaw("event_data->>'session_number' = ?", [$sessionNumber])
            ->pluck('related_charge_id')
            ->unique();
        
        $orphanedCharges = ConnectedCharge::where('stripe_account_id', $stripeAccountId)
            ->whereNull('pos_session_id')
            ->where('status', 'succeeded')
            ->where(function ($query) use ($sessionNumber, $chargeIdsFromEvents, $sessionStart, $sessionEnd) {
                // Check if charge metadata contains the session number (using ->' for JSON, ->>' for text)
                $query->whereRaw("metadata->>'pos_session_number' = ?", [$sessionNumber])
                    // Or check if charge has receipts with this session number
                    ->orWhereHas('receipts', function ($q) use ($sessionNumber) {
                        $q->whereRaw("receipt_data->>'session_number' = ?", [$sessionNumber]);
                    })
                    // Or check if charge has events with this session number
                    ->orWhereIn('id', $chargeIdsFromEvents)
                    // Fallback: also check date range for truly orphaned charges without session_number
                    ->orWhere(function ($q) use ($sessionStart, $sessionEnd, $sessionNumber) {
                        $q->where(function ($q2) use ($sessionStart, $sessionEnd) {
                            $q2->whereNotNull('paid_at')
                                ->whereBetween('paid_at', [$sessionStart, $sessionEnd]);
                        })
                        ->orWhere(function ($q2) use ($sessionStart, $sessionEnd) {
                            $q2->whereNull('paid_at')
                                ->whereBetween('created_at', [$sessionStart, $sessionEnd]);
                        })
                        // Only include if metadata doesn't have a different session number
                        ->where(function ($q2) use ($sessionNumber) {
                            $q2->whereRaw("metadata->>'pos_session_number' IS NULL")
                                ->orWhereRaw("metadata->>'pos_session_number' != ?", [$sessionNumber]);
                        });
                    });
            })
            ->get()
            ->filter(function ($charge) use ($sessionNumber, $sessionStart, $sessionEnd) {
                // Double-check: only include if session_number matches
                // Check metadata
                $metadataSessionNumber = $charge->metadata['pos_session_number'] ?? null;
                if ($metadataSessionNumber === $sessionNumber) {
                    return true;
                }
                
                // Check receipts
                $hasMatchingReceipt = $charge->receipts()
                    ->whereRaw("receipt_data->>'session_number' = ?", [$sessionNumber])
                    ->exists();
                if ($hasMatchingReceipt) {
                    return true;
                }
                
                // Check events
                $hasMatchingEvent = PosEvent::where('related_charge_id', $charge->id)
                    ->whereRaw("event_data->>'session_number' = ?", [$sessionNumber])
                    ->exists();
                if ($hasMatchingEvent) {
                    return true;
                }
                
                // If no session_number found anywhere, check date range as fallback
                // but only if metadata doesn't have a different session number
                if ($metadataSessionNumber === null || $metadataSessionNumber === '') {
                    $chargeDate = $charge->paid_at ?? $charge->created_at;
                    return $chargeDate >= $sessionStart && $chargeDate <= $sessionEnd;
                }
                
                return false;
            });

        foreach ($orphanedCharges as $charge) {
            if (!$dryRun) {
                $charge->pos_session_id = $session->id;
                $charge->save();
            }
            $countedChargeIds[$charge->id] = true;
            $stats['charges_found']++;
        }

        // Method 2: Find charges via receipts - match by session_number in receipt_data
        $receiptsWithoutSession = Receipt::where('store_id', $store->id)
            ->whereNull('pos_session_id')
            ->whereNotNull('charge_id')
            ->whereRaw("receipt_data->>'session_number' = ?", [$sessionNumber])
            ->get();

        foreach ($receiptsWithoutSession as $receipt) {
            if (!$dryRun) {
                $receipt->pos_session_id = $session->id;
                $receipt->save();
            }

            // Also link the charge if it's not already linked
            // Check and count in both dry_run and non-dry_run modes
            if ($receipt->charge_id) {
                $charge = ConnectedCharge::find($receipt->charge_id);
                // In dry-run mode, also check if we've already counted this charge
                $alreadyCounted = $charge && $dryRun && isset($countedChargeIds[$charge->id]);
                if ($charge && !$charge->pos_session_id && !$alreadyCounted) {
                    if (!$dryRun) {
                        $charge->pos_session_id = $session->id;
                        $charge->save();
                    }
                    $countedChargeIds[$charge->id] = true;
                    $stats['charges_found']++;
                }
            }
            $stats['receipts_found']++;
        }

        // Method 3: Find charges via events - match by session_number in event_data or pos_device_id
        $eventsWithoutSession = PosEvent::where('store_id', $store->id)
            ->whereNull('pos_session_id')
            ->whereNotNull('related_charge_id')
            ->where(function ($query) use ($sessionNumber, $session) {
                // Match by session_number in event_data
                $query->whereRaw("event_data->>'session_number' = ?", [$sessionNumber])
                    // Or match by device if session has a device
                    ->orWhere(function ($q) use ($session) {
                        if ($session->pos_device_id) {
                            $q->where('pos_device_id', $session->pos_device_id)
                                ->whereBetween('occurred_at', [$session->opened_at, $session->closed_at ?? now()]);
                        }
                    });
            })
            ->get();

        foreach ($eventsWithoutSession as $event) {
            if (!$dryRun) {
                $event->pos_session_id = $session->id;
                $event->save();
            }

            // Also link the charge if it's not already linked
            // Check and count in both dry_run and non-dry_run modes
            if ($event->related_charge_id) {
                $charge = ConnectedCharge::find($event->related_charge_id);
                // In dry-run mode, also check if we've already counted this charge
                $alreadyCounted = $charge && $dryRun && isset($countedChargeIds[$charge->id]);
                if ($charge && !$charge->pos_session_id && !$alreadyCounted) {
                    if (!$dryRun) {
                        $charge->pos_session_id = $session->id;
                        $charge->save();
                    }
                    $countedChargeIds[$charge->id] = true;
                    $stats['charges_found']++;
                }
            }
            $stats['events_found']++;
        }

        // Method 4: Find receipts that might belong to this session by date
        // (receipts that have a charge linked to this session but receipt itself isn't linked)
        $chargesInSession = $session->charges()->pluck('id');
        if ($chargesInSession->isNotEmpty()) {
            $receiptsByCharge = Receipt::where('store_id', $store->id)
                ->whereNull('pos_session_id')
                ->whereIn('charge_id', $chargesInSession)
                ->get();

            foreach ($receiptsByCharge as $receipt) {
                if (!$dryRun) {
                    $receipt->pos_session_id = $session->id;
                    $receipt->save();
                }
                $stats['receipts_found']++;
            }
        }

        return $stats;
    }
}
