<?php

namespace App\Listeners;

use App\Jobs\SyncConnectedProductToStripeJob;
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
        $syncableFields = [
            'name',
            'description',
            'active',
            'images',
            'product_meta',
            'url',
            'package_dimensions',
            'shippable',
            'statement_descriptor',
            'tax_code',
            'unit_label',
            'default_price',
            'price',
            'currency',
        ];
        $hasChanges = false;

        foreach ($syncableFields as $field) {
            if ($product->wasChanged($field)) {
                $hasChanges = true;
                break;
            }
        }

        // Also check if media library images changed by comparing media count with images array
        // Media changes are detected by checking if media collection count differs from images array
        if (! $hasChanges) {
            $mediaCount = $product->getMedia('images')->count();
            $currentImages = is_array($product->images) ? $product->images : [];
            $imagesCount = count($currentImages);
            
            // If counts don't match, media was added or removed
            if ($mediaCount !== $imagesCount) {
                $hasChanges = true;
            }
            
            // Also check if product has media but images field is empty (media was added)
            if ($mediaCount > 0 && empty($currentImages)) {
                $hasChanges = true;
            }
            
            // Also check if images field has URLs but no media (media was removed)
            if ($mediaCount === 0 && !empty($currentImages)) {
                $hasChanges = true;
            }
        }

        if (! $hasChanges) {
            return;
        }

        SyncConnectedProductToStripeJob::dispatch($product);
    }
}

