<?php

namespace App\Listeners;

use App\Actions\ConnectedProducts\UpdateConnectedProductToStripe;
use App\Models\ConnectedProduct;

class SyncConnectedProductToStripeListener
{
    /**
     * Handle the event.
     */
    public function handle(ConnectedProduct $product): void
    {
        // Only sync if product has Stripe IDs and relevant fields changed
        if (! $product->stripe_product_id || ! $product->stripe_account_id) {
            return;
        }

        // Check if any syncable fields changed using wasChanged()
        $syncableFields = ['name', 'description', 'active', 'images', 'metadata', 'url'];
        $hasChanges = false;

        foreach ($syncableFields as $field) {
            if ($product->wasChanged($field)) {
                $hasChanges = true;
                break;
            }
        }

        if (! $hasChanges) {
            return;
        }

        $action = new UpdateConnectedProductToStripe();
        $action($product);
    }
}

