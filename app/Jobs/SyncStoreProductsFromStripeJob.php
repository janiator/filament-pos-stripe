<?php

namespace App\Jobs;

use App\Actions\ConnectedProducts\SyncConnectedProductsFromStripe;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncStoreProductsFromStripeJob implements ShouldQueue
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
    public int $timeout = 600; // 10 minutes

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
        Log::info('Starting sync products from Stripe for store', [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
        ]);

        try {
            // Refresh the store to ensure we have the latest data
            $this->store->refresh();
            
            // Skip if store doesn't have stripe_account_id
            if (!$this->store->stripe_account_id) {
                Log::warning('Skipping sync - store does not have stripe_account_id', [
                    'store_id' => $this->store->id,
                ]);
                return;
            }

            $syncAction = new SyncConnectedProductsFromStripe();
            $result = $syncAction($this->store, false); // Don't send notifications from job

            Log::info('Sync products from Stripe completed for store', [
                'store_id' => $this->store->id,
                'total' => $result['total'],
                'created' => $result['created'],
                'updated' => $result['updated'],
                'errors_count' => count($result['errors']),
            ]);
        } catch (\Throwable $e) {
            Log::error('Sync products from Stripe job failed for store', [
                'store_id' => $this->store->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }
}
