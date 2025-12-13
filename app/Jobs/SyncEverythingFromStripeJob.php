<?php

namespace App\Jobs;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class SyncEverythingFromStripeJob implements ShouldQueue
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
     * The batch size for dispatching jobs.
     */
    public int $batchSize = 50;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting sync everything from Stripe job - dispatching smaller jobs in batches');

        try {
            // First, sync stores synchronously to ensure we have the latest store data
            // This is important because new stores might exist in Stripe
            Log::info('Syncing stores from Stripe');
            $storeSyncJob = new SyncStoresFromStripeJob();
            $storeSyncJob->handle();

            // Get stores after syncing to get any newly created stores
            $stores = Store::getStoresForSync();

            if ($stores->isEmpty()) {
                Log::warning('No stores found for sync after syncing stores');
                return;
            }

            Log::info('Found stores for sync', [
                'store_count' => $stores->count(),
            ]);

            // Collect all jobs to dispatch
            $jobs = collect();

            // For each store, create jobs for each sync type
            foreach ($stores as $store) {
                // Refresh store to ensure we have latest data
                $store->refresh();

                // Skip if store doesn't have stripe_account_id
                if (!$store->stripe_account_id) {
                    Log::debug('Skipping store without stripe_account_id', [
                        'store_id' => $store->id,
                    ]);
                    continue;
                }

                // Add all sync jobs for this store
                $jobs->push(
                    new SyncStoreCustomersFromStripeJob($store),
                    new SyncStoreProductsFromStripeJob($store),
                    new SyncStoreSubscriptionsFromStripeJob($store),
                    new SyncStorePaymentIntentsFromStripeJob($store),
                    new SyncStoreChargesFromStripeJob($store),
                    new SyncStoreTransfersFromStripeJob($store),
                    new SyncStorePaymentMethodsFromStripeJob($store),
                    new SyncStorePaymentLinksFromStripeJob($store),
                    new SyncStoreTerminalLocationsFromStripeJob($store),
                    new SyncStoreTerminalReadersFromStripeJob($store),
                );
            }

            if ($jobs->isEmpty()) {
                Log::warning('No sync jobs to dispatch - all stores may be missing stripe_account_id');
                return;
            }

            Log::info('Dispatching sync jobs in batches', [
                'total_jobs' => $jobs->count(),
                'batch_size' => $this->batchSize,
            ]);

            // Dispatch jobs in batches
            $batchesCreated = 0;
            $jobs->chunk($this->batchSize)->each(function (Collection $chunk) use (&$batchesCreated) {
                Bus::batch($chunk->toArray())
                    ->name('Sync from Stripe - Batch ' . ($batchesCreated + 1))
                    ->dispatch();
                $batchesCreated++;
            });

            Log::info('Sync everything from Stripe job completed - all sync jobs dispatched', [
                'total_jobs_dispatched' => $jobs->count(),
                'batches_created' => $batchesCreated,
            ]);
        } catch (\Throwable $e) {
            Log::error('Sync everything from Stripe job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }
}

