<?php

namespace App\Jobs;

use App\Actions\Stores\SyncStoreTerminalLocationsFromStripe;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncStoreTerminalLocationsFromStripeJob implements ShouldQueue
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
    public int $timeout = 300; // 5 minutes

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
        Log::info('Starting sync terminal locations from Stripe for store', [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
        ]);

        try {
            $syncAction = new SyncStoreTerminalLocationsFromStripe();
            $result = $syncAction($this->store);

            Log::info('Sync terminal locations from Stripe completed for store', [
                'store_id' => $this->store->id,
                'total' => $result['total'],
                'created' => $result['created'],
                'updated' => $result['updated'],
                'has_error' => !empty($result['error']),
            ]);
        } catch (\Throwable $e) {
            Log::error('Sync terminal locations from Stripe job failed for store', [
                'store_id' => $this->store->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }
}
