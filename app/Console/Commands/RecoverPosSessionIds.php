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
        $failedCharges = [];

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
            if (!$sessionId && $fromSessions && $charge->paid_at) {
                // Find store by stripe_account_id
                $store = \App\Models\Store::where('stripe_account_id', $charge->stripe_account_id)->first();
                
                if ($store) {
                    $session = PosSession::where('store_id', $store->id)
                        ->where('opened_at', '<=', $charge->paid_at)
                        ->where(function ($query) use ($charge) {
                            $query->whereNull('closed_at')
                                ->orWhere('closed_at', '>=', $charge->paid_at);
                        })
                        ->orderBy('opened_at', 'desc')
                        ->first();
                } else {
                    $session = null;
                }

                if ($session) {
                    $sessionId = $session->id;
                    $this->line("Charge {$charge->id}: Matched to session {$sessionId} by date/time");
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
                $reason = [];
                if (!$charge->paid_at) {
                    $reason[] = 'no paid_at date';
                } else {
                    $reason[] = 'no matching session found';
                }
                $failedCharges[] = [
                    'id' => $charge->id,
                    'paid_at' => $charge->paid_at?->format('Y-m-d H:i:s'),
                    'amount' => $charge->amount,
                    'reason' => implode(', ', $reason),
                ];
                $this->warn("Charge {$charge->id}: Could not recover session ID" . ($charge->paid_at ? '' : ' (no paid_at date)'));
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Recovery complete!");
        $this->info("Recovered: {$recovered}");
        $this->info("Failed: {$failed}");

        if ($failed > 0) {
            $this->newLine();
            if (count($failedCharges) > 0) {
                $this->warn("Failed charges (showing first 10):");
                foreach (array_slice($failedCharges, 0, 10) as $failedCharge) {
                    $paidAt = $failedCharge['paid_at'] ?? 'N/A';
                    $amount = $failedCharge['amount'] ? ($failedCharge['amount'] / 100) . ' NOK' : 'N/A';
                    $this->line("  Charge ID: {$failedCharge['id']}, Paid: {$paidAt}, Amount: {$amount}, Reason: {$failedCharge['reason']}");
                }
                if (count($failedCharges) > 10) {
                    $this->line("  ... and " . (count($failedCharges) - 10) . " more");
                }
            } else {
                $this->warn("Note: Failed charges details not available.");
            }
        }

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

