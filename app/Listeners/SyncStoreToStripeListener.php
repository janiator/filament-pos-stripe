<?php

namespace App\Listeners;

use App\Actions\Stores\SyncStoreToStripe;
use App\Models\Store;

class SyncStoreToStripeListener
{
    /**
     * Handle the event.
     */
    public function handle(Store $store): void
    {
        // Only sync if store has a Stripe account and relevant fields changed
        if (! $store->stripe_account_id) {
            \Log::debug('SyncStoreToStripeListener: Store has no stripe_account_id', ['store_id' => $store->id]);
            return;
        }

        // Check if any syncable fields changed using wasChanged()
        // Note: email cannot be updated via Stripe Connect API, only name can be synced
        $syncableFields = ['name'];
        $hasChanges = false;
        $changedFields = [];

        foreach ($syncableFields as $field) {
            if ($store->wasChanged($field)) {
                $hasChanges = true;
                $original = $store->getOriginal($field);
                $current = $store->getAttribute($field);
                $changedFields[$field] = ['original' => $original, 'current' => $current];
            }
        }

        if (! $hasChanges) {
            \Log::debug('SyncStoreToStripeListener: No syncable fields changed', [
                'store_id' => $store->id,
                'dirty' => $store->getDirty(),
                'was_changed' => $store->getChanges(),
            ]);
            return;
        }

        \Log::info('SyncStoreToStripeListener: Syncing store to Stripe', [
            'store_id' => $store->id,
            'stripe_account_id' => $store->stripe_account_id,
            'changed_fields' => $changedFields,
        ]);

        try {
            $action = new SyncStoreToStripe();
            $action($store);
            \Log::info('SyncStoreToStripeListener: Successfully synced store to Stripe', ['store_id' => $store->id]);
        } catch (\Throwable $e) {
            \Log::error('SyncStoreToStripeListener: Failed to sync store to Stripe', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}

