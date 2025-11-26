<?php

namespace App\Jobs;

use App\Actions\Stores\SyncStoreToStripe;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncStoreToStripeJob implements ShouldQueue
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
        Log::info('SyncStoreToStripeJob: Starting sync', [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'stripe_account_id' => $this->store->stripe_account_id,
            'store_email' => $this->store->email,
            'attempt' => $this->attempts(),
        ]);

        try {
            $action = new SyncStoreToStripe();
            $action($this->store);

            Log::info('SyncStoreToStripeJob: Successfully synced store to Stripe', [
                'store_id' => $this->store->id,
                'stripe_account_id' => $this->store->stripe_account_id,
            ]);
        } catch (Throwable $e) {
            Log::error('SyncStoreToStripeJob: Failed to sync store to Stripe', [
                'store_id' => $this->store->id,
                'stripe_account_id' => $this->store->stripe_account_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('SyncStoreToStripeJob: Job failed permanently', [
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}

