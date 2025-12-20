<?php

namespace App\Console\Commands;

use App\Models\ConnectedCharge;
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
                            {--from-sessions : Try to match by date/time and stripe_account_id}';

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
        $fromSessions = $this->option('from-sessions');

        // If no specific method specified, try both
        if (!$fromReceipts && !$fromSessions) {
            $fromReceipts = true;
            $fromSessions = true;
        }

        $this->info('Recovering pos_session_id for charges...');
        $this->newLine();

        $chargesWithoutSession = ConnectedCharge::whereNull('pos_session_id')
            ->whereNotNull('stripe_account_id')
            ->get();

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

            // Method 2: Try to match by date/time and stripe_account_id
            if (!$sessionId && $fromSessions && $charge->paid_at) {
                $session = PosSession::where('store_id', function ($query) use ($charge) {
                    $query->select('id')
                        ->from('stores')
                        ->where('stripe_account_id', $charge->stripe_account_id)
                        ->limit(1);
                })
                    ->where('opened_at', '<=', $charge->paid_at)
                    ->where(function ($query) use ($charge) {
                        $query->whereNull('closed_at')
                            ->orWhere('closed_at', '>=', $charge->paid_at);
                    })
                    ->orderBy('opened_at', 'desc')
                    ->first();

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
                $this->warn("Charge {$charge->id}: Could not recover session ID");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Recovery complete!");
        $this->info("Recovered: {$recovered}");
        $this->info("Failed: {$failed}");

        if ($dryRun) {
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

