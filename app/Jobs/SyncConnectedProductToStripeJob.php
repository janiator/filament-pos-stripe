<?php

namespace App\Jobs;

use App\Actions\ConnectedProducts\UpdateConnectedProductToStripe;
use App\Models\ConnectedProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncConnectedProductToStripeJob implements ShouldQueue
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
        public ConnectedProduct $product
    ) {
        // Set queue name
        $this->onQueue('stripe-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('SyncConnectedProductToStripeJob: Starting sync', [
            'product_id' => $this->product->id,
            'stripe_product_id' => $this->product->stripe_product_id,
            'stripe_account_id' => $this->product->stripe_account_id,
            'product_name' => $this->product->name,
            'attempt' => $this->attempts(),
        ]);

        try {
            $action = new UpdateConnectedProductToStripe();
            $action($this->product);

            Log::info('SyncConnectedProductToStripeJob: Successfully synced product to Stripe', [
                'product_id' => $this->product->id,
                'stripe_product_id' => $this->product->stripe_product_id,
                'stripe_account_id' => $this->product->stripe_account_id,
            ]);
        } catch (Throwable $e) {
            Log::error('SyncConnectedProductToStripeJob: Failed to sync product to Stripe', [
                'product_id' => $this->product->id,
                'stripe_product_id' => $this->product->stripe_product_id,
                'stripe_account_id' => $this->product->stripe_account_id,
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
        Log::error('SyncConnectedProductToStripeJob: Job failed permanently', [
            'product_id' => $this->product->id,
            'stripe_product_id' => $this->product->stripe_product_id,
            'stripe_account_id' => $this->product->stripe_account_id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}

