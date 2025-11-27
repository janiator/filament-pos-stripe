<?php

namespace App\Jobs;

use App\Actions\ConnectedProducts\DeleteConnectedProductFromStripe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteConnectedProductFromStripeJob implements ShouldQueue
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
        public string $stripeProductId,
        public string $stripeAccountId,
        public ?int $productId = null,
        public ?string $productName = null
    ) {
        // Set queue name
        $this->onQueue('stripe-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(DeleteConnectedProductFromStripe $deleteAction): void
    {
        Log::info('DeleteConnectedProductFromStripeJob: Starting deletion', [
            'product_id' => $this->productId,
            'stripe_product_id' => $this->stripeProductId,
            'stripe_account_id' => $this->stripeAccountId,
            'product_name' => $this->productName,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Create a temporary product object with the necessary data
            // We can't use the actual model since it's been deleted
            $product = new \App\Models\ConnectedProduct([
                'id' => $this->productId,
                'stripe_product_id' => $this->stripeProductId,
                'stripe_account_id' => $this->stripeAccountId,
                'name' => $this->productName,
            ]);

            $deleteAction($product);

            Log::info('DeleteConnectedProductFromStripeJob: Successfully archived product in Stripe', [
                'product_id' => $this->productId,
                'stripe_product_id' => $this->stripeProductId,
                'stripe_account_id' => $this->stripeAccountId,
            ]);
        } catch (Throwable $e) {
            Log::error('DeleteConnectedProductFromStripeJob: Failed to archive product in Stripe', [
                'product_id' => $this->productId,
                'stripe_product_id' => $this->stripeProductId,
                'stripe_account_id' => $this->stripeAccountId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw - allow deletion to proceed even if Stripe archiving fails
            // The error is already logged
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('DeleteConnectedProductFromStripeJob: Job failed permanently', [
            'product_id' => $this->productId,
            'stripe_product_id' => $this->stripeProductId,
            'stripe_account_id' => $this->stripeAccountId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}

