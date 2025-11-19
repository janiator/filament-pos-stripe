<?php

namespace App\Listeners;

use App\Actions\ConnectedSubscriptions\UpdateConnectedSubscriptionToStripe;
use App\Models\ConnectedSubscription;

class SyncConnectedSubscriptionToStripeListener
{
    /**
     * Handle the event.
     */
    public function handle(ConnectedSubscription $subscription): void
    {
        // Only sync if subscription has Stripe IDs and relevant fields changed
        if (! $subscription->stripe_id || ! $subscription->stripe_account_id) {
            return;
        }

        // Check if any syncable fields changed using wasChanged()
        $syncableFields = ['cancel_at_period_end', 'metadata'];
        $hasChanges = false;

        foreach ($syncableFields as $field) {
            if ($subscription->wasChanged($field)) {
                $hasChanges = true;
                break;
            }
        }

        if (! $hasChanges) {
            return;
        }

        $action = new UpdateConnectedSubscriptionToStripe();
        $action($subscription);
    }
}

