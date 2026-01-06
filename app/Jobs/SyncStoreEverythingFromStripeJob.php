<?php

namespace App\Jobs;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class SyncStoreEverythingFromStripeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes (reduced since we're just dispatching)

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Store $store
    ) {
        // Set queue name
        $this->onQueue('stripe-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting sync everything from Stripe job for store', [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
        ]);

        try {
            // Refresh store to ensure we have latest data
            $this->store->refresh();

            // Skip if store doesn't have stripe_account_id
            if (!$this->store->stripe_account_id) {
                Log::warning('Skipping sync - store does not have stripe_account_id', [
                    'store_id' => $this->store->id,
                ]);
                return;
            }

            // Dispatch all sync jobs for this store as a batch
            $jobs = [
                new SyncStoreCustomersFromStripeJob($this->store),
                new SyncStoreProductsFromStripeJob($this->store),
                new SyncStoreSubscriptionsFromStripeJob($this->store),
                new SyncStorePaymentIntentsFromStripeJob($this->store),
                new SyncStoreChargesFromStripeJob($this->store),
                new SyncStoreTransfersFromStripeJob($this->store),
                new SyncStorePaymentMethodsFromStripeJob($this->store),
                new SyncStorePaymentLinksFromStripeJob($this->store),
                new SyncStoreTerminalLocationsFromStripeJob($this->store),
                new SyncStoreTerminalReadersFromStripeJob($this->store),
            ];

            Log::info('Dispatching sync jobs batch for store', [
                'store_id' => $this->store->id,
                'total_jobs' => count($jobs),
            ]);

            // Dispatch all jobs as a batch
            Bus::batch($jobs)
                ->name("Sync from Stripe - {$this->store->name}")
                ->dispatch();

            Log::info('Sync everything from Stripe job completed - all sync jobs dispatched for store', [
                'store_id' => $this->store->id,
                'total_jobs_dispatched' => count($jobs),
            ]);
        } catch (\Throwable $e) {
            Log::error('Sync everything from Stripe job failed for store', [
                'store_id' => $this->store->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }
}
