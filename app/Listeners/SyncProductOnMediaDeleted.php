<?php

namespace App\Listeners;

use App\Jobs\SyncConnectedProductToStripeJob;
use App\Models\ConnectedProduct;
use Spatie\MediaLibrary\MediaCollections\Events\CollectionHasBeenClearedEvent;

class SyncProductOnMediaDeleted
{
    /**
     * Handle the event when a media collection is cleared.
     */
    public function handle(CollectionHasBeenClearedEvent $event): void
    {
        // Only handle if this is from the images collection
        if ($event->collectionName !== 'images') {
            return;
        }
        
        // Get the model that owns this media collection
        $model = $event->model;
        
        // Only sync if it's a ConnectedProduct with Stripe IDs
        if (!($model instanceof ConnectedProduct)) {
            return;
        }
        
        /** @var ConnectedProduct $product */
        $product = $model;
        
        if (! $product->stripe_product_id || ! $product->stripe_account_id) {
            return;
        }
        
        // Refresh the product to get updated media count
        $product->refresh();
        
        // Dispatch sync job to update Stripe with removed images
        SyncConnectedProductToStripeJob::dispatch($product);
    }
}

