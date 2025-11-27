<?php

namespace App\Jobs;

use App\Actions\ConnectedProducts\UpdateVariantProductToStripe;
use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVariantProductToStripeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ProductVariant $variant
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->variant->stripe_product_id || !$this->variant->stripe_account_id) {
            return;
        }

        $updateAction = new UpdateVariantProductToStripe();
        $updateAction($this->variant);
    }
}

