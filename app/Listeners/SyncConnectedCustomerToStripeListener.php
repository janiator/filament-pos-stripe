<?php

namespace App\Listeners;

use App\Actions\ConnectedCustomers\UpdateConnectedCustomerToStripe;
use App\Models\ConnectedCustomer;

class SyncConnectedCustomerToStripeListener
{
    /**
     * Handle the event.
     */
    public function handle(ConnectedCustomer $customer): void
    {
        // Only sync if customer has Stripe IDs and relevant fields changed
        if (! $customer->stripe_customer_id || ! $customer->stripe_account_id) {
            return;
        }

        // Check if any syncable fields changed using wasChanged()
        $syncableFields = ['name', 'email'];
        $hasChanges = false;

        foreach ($syncableFields as $field) {
            if ($customer->wasChanged($field)) {
                $hasChanges = true;
                break;
            }
        }

        if (! $hasChanges) {
            return;
        }

        \App\Jobs\SyncConnectedCustomerToStripeJob::dispatch($customer);
    }
}

