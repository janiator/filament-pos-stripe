<?php

namespace App\Console\Commands;

use App\Models\ConnectedCharge;
use App\Models\PosEvent;
use App\Models\PosSession;
use App\Models\Receipt;
use Illuminate\Console\Command;

class RecoverPosSessionIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:recover-session-ids 
                            {--dry-run : Show what would be updated without actually updating}
                            {--from-receipts : Try to recover from receipts table}
                            {--from-events : Try to recover from pos_events table}
                            {--from-sessions : Try to match by date/time and stripe_account_id}
                            {--store= : Filter by store ID or slug (e.g., --store=1 or --store=my-store)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recover pos_session_id for charges that have lost their session association';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fromReceipts = $this->option('from-receipts');
        $fromEvents = $this->option('from-events');
        $fromSessions = $this->option('from-sessions');
        $storeFilter = $this->option('store');

        // If no specific method specified, try all
        if (!$fromReceipts && !$fromEvents && !$fromSessions) {
            $fromReceipts = true;
            $fromEvents = true;
            $fromSessions = true;
        }

        // Resolve store if filter provided
        $store = null;
        if ($storeFilter) {
            $store = \App\Models\Store::where('id', $storeFilter)
                ->orWhere('slug', $storeFilter)
                ->first();

            if (!$store) {
                $this->error("Store not found: {$storeFilter}");
                $this->newLine();
                $this->info("Available stores:");
                $stores = \App\Models\Store::select('id', 'name', 'slug')->get();
                foreach ($stores as $s) {
                    $this->line("  ID: {$s->id}, Slug: {$s->slug}, Name: {$s->name}");
                }
                return Command::FAILURE;
            }

            $this->info("Filtering by store: {$store->name} (ID: {$store->id}, Slug: {$store->slug})");
            $this->newLine();
        }

        $this->info('Recovering pos_session_id for charges...');
        $this->newLine();

        $chargesQuery = ConnectedCharge::whereNull('pos_session_id')
            ->whereNotNull('stripe_account_id');

        // Filter by store if specified
        if ($store) {
            $chargesQuery->where('stripe_account_id', $store->stripe_account_id);
        }

        $chargesWithoutSession = $chargesQuery->get();

        $this->info("Found {$chargesWithoutSession->count()} charges without pos_session_id");
        $this->newLine();

        $recovered = 0;
        $failed = 0;

        foreach ($chargesWithoutSession as $charge) {
            $sessionId = null;

            // Method 1: Try to recover from receipts
            if ($fromReceipts) {
                $receipt = Receipt::where('charge_id', $charge->id)
                    ->whereNotNull('pos_session_id')
                    ->first();

                if ($receipt) {
                    $sessionId = $receipt->pos_session_id;
                    $this->line("Charge {$charge->id}: Found session {$sessionId} from receipt {$receipt->id}");
                }
            }

            // Method 2: Try to recover from pos_events
            if (!$sessionId && $fromEvents) {
                $event = PosEvent::where('related_charge_id', $charge->id)
                    ->whereNotNull('pos_session_id')
                    ->first();

                if ($event) {
                    $sessionId = $event->pos_session_id;
                    $this->line("Charge {$charge->id}: Found session {$sessionId} from event {$event->id} (code: {$event->event_code})");
                }
            }

            // Method 3: Try to match by date/time and stripe_account_id
            // Use paid_at if available, otherwise fall back to created_at (for deferred payments)
            $matchDate = $charge->paid_at ?? $charge->created_at;
            if (!$sessionId && $fromSessions && $matchDate) {
                // Find store by stripe_account_id
                $store = \App\Models\Store::where('stripe_account_id', $charge->stripe_account_id)->first();
                
                if ($store) {
                    $session = PosSession::where('store_id', $store->id)
                        ->where('opened_at', '<=', $matchDate)
                        ->where(function ($query) use ($matchDate) {
                            $query->whereNull('closed_at')
                                ->orWhere('closed_at', '>=', $matchDate);
                        })
                        ->orderBy('opened_at', 'desc')
                        ->first();
                } else {
                    $session = null;
                }

                if ($session) {
                    $sessionId = $session->id;
                    $dateType = $charge->paid_at ? 'paid_at' : 'created_at';
                    $this->line("Charge {$charge->id}: Matched to session {$sessionId} by date/time (using {$dateType})");
                }
            }

            if ($sessionId) {
                if (!$dryRun) {
                    $charge->pos_session_id = $sessionId;
                    
                    // Also try to recover transaction_code and payment_code if missing
                    if (empty($charge->transaction_code) || empty($charge->payment_code)) {
                        $this->recoverSafTCodes($charge);
                    }
                    
                    $charge->save();
                }
                $recovered++;
            } else {
                // Determine why recovery failed and output immediately
                $reason = 'unknown reason';
                $matchDate = $charge->paid_at ?? $charge->created_at;
                
                if (!$matchDate) {
                    $reason = 'no paid_at or created_at date';
                } elseif (!$fromSessions) {
                    $reason = 'date/time matching disabled';
                } else {
                    // Check if there are any sessions at all for this store
                    $store = \App\Models\Store::where('stripe_account_id', $charge->stripe_account_id)->first();
                    if ($store) {
                        $sessionCount = PosSession::where('store_id', $store->id)->count();
                        if ($sessionCount === 0) {
                            $reason = 'no sessions exist for this store';
                        } else {
                            // Check if charge date is outside all session windows
                            $earliestSession = PosSession::where('store_id', $store->id)
                                ->orderBy('opened_at', 'asc')
                                ->first();
                            $latestSession = PosSession::where('store_id', $store->id)
                                ->whereNotNull('closed_at')
                                ->orderBy('closed_at', 'desc')
                                ->first();
                            
                            if ($earliestSession && $matchDate < $earliestSession->opened_at) {
                                $dateType = $charge->paid_at ? 'paid_at' : 'created_at';
                                $reason = 'charge date (' . $matchDate->format('Y-m-d H:i:s') . ' from ' . $dateType . ') before earliest session (' . $earliestSession->opened_at->format('Y-m-d H:i:s') . ')';
                            } elseif ($latestSession && $matchDate > $latestSession->closed_at) {
                                $dateType = $charge->paid_at ? 'paid_at' : 'created_at';
                                $reason = 'charge date (' . $matchDate->format('Y-m-d H:i:s') . ' from ' . $dateType . ') after latest closed session (' . $latestSession->closed_at->format('Y-m-d H:i:s') . ')';
                            } else {
                                $dateType = $charge->paid_at ? 'paid_at' : 'created_at';
                                $reason = 'no matching session found (date ' . $matchDate->format('Y-m-d H:i:s') . ' from ' . $dateType . ' falls between sessions)';
                            }
                        }
                    } else {
                        $reason = 'store not found';
                    }
                }
                
                $paidAtStr = $charge->paid_at ? $charge->paid_at->format('Y-m-d H:i:s') : 'N/A';
                $createdAtStr = $charge->created_at ? $charge->created_at->format('Y-m-d H:i:s') : 'N/A';
                $amountStr = $charge->amount ? number_format($charge->amount / 100, 2) . ' NOK' : 'N/A';
                
                $this->warn("Charge {$charge->id}: Could not recover session ID - {$reason} (Paid: {$paidAtStr}, Created: {$createdAtStr}, Amount: {$amountStr})");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Recovery complete!");
        $this->info("Recovered: {$recovered}");
        $this->info("Failed: {$failed}");

        if ($dryRun) {
            $this->newLine();
            $this->warn("This was a dry run. Run without --dry-run to apply changes.");
        }

        return Command::SUCCESS;
    }

    /**
     * Recover SAF-T codes for a charge
     */
    protected function recoverSafTCodes(ConnectedCharge $charge): void
    {
        if (empty($charge->payment_method)) {
            return;
        }

        try {
            $safTCodeMapper = app(\App\Services\SafTCodeMapper::class);

            if (empty($charge->payment_code)) {
                $charge->payment_code = $safTCodeMapper->mapPaymentMethodToCode($charge->payment_method);
            }

            if (empty($charge->transaction_code)) {
                $charge->transaction_code = $safTCodeMapper->mapTransactionToCode($charge);
            }
        } catch (\Throwable $e) {
            // Silently fail - codes will be set by observer if needed
        }
    }
}

