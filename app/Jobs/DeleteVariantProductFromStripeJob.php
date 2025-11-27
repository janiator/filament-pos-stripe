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

class DeleteVariantProductFromStripeJob implements ShouldQueue
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
        public ?int $variantId = null
    ) {
        // Set queue name
        $this->onQueue('stripe-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(DeleteConnectedProductFromStripe $deleteAction): void
    {
        Log::info('DeleteVariantProductFromStripeJob: Starting deletion', [
            'variant_id' => $this->variantId,
            'stripe_product_id' => $this->stripeProductId,
            'stripe_account_id' => $this->stripeAccountId,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Create a temporary product object with the necessary data
            // We can't use the actual variant model since it's been deleted
            $product = new \App\Models\ConnectedProduct([
                'id' => $this->variantId,
                'stripe_product_id' => $this->stripeProductId,
                'stripe_account_id' => $this->stripeAccountId,
            ]);

            $deleteAction($product);

            Log::info('DeleteVariantProductFromStripeJob: Successfully archived variant product in Stripe', [
                'variant_id' => $this->variantId,
                'stripe_product_id' => $this->stripeProductId,
                'stripe_account_id' => $this->stripeAccountId,
            ]);
        } catch (Throwable $e) {
            Log::error('DeleteVariantProductFromStripeJob: Failed to archive variant product in Stripe', [
                'variant_id' => $this->variantId,
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
        Log::error('DeleteVariantProductFromStripeJob: Job failed permanently', [
            'variant_id' => $this->variantId,
            'stripe_product_id' => $this->stripeProductId,
            'stripe_account_id' => $this->stripeAccountId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}

