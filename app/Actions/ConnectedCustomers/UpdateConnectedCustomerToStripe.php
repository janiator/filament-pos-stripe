<?php

namespace App\Actions\ConnectedCustomers;

use App\Models\ConnectedCustomer;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;

class UpdateConnectedCustomerToStripe
{
    public function __invoke(ConnectedCustomer $customer): void
    {
        if (! $customer->stripe_customer_id || ! $customer->stripe_account_id) {
            return;
        }

        $store = Store::where('stripe_account_id', $customer->stripe_account_id)->first();
        if (! $store || ! $store->hasStripeAccount()) {
            return;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (! $secret) {
            return;
        }

        $stripe = new StripeClient($secret);

        try {
            $updateData = [];

            // Add name if it exists
            if ($customer->name) {
                $updateData['name'] = $customer->name;
            }
            
            // Add email if it exists
            if ($customer->email) {
                $updateData['email'] = $customer->email;
            }

            if (! empty($updateData)) {
                $stripe->customers->update(
                    $customer->stripe_customer_id,
                    $updateData,
                    ['stripe_account' => $customer->stripe_account_id]
                );
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}

