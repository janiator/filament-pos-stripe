<?php

namespace App\Actions\Stores;

use App\Models\Store;
use Stripe\StripeClient;
use Throwable;

class SyncStoreToStripe
{
    public function __invoke(Store $store): void
    {
        $secret = config('cashier.secret') ?? config('services.stripe.secret');

        if (! $secret) {
            // You might want to log or throw here
            return;
        }

        $stripe = new StripeClient($secret);

        try {
            // If the Store has no connected account yet, create one
            if (! $store->stripe_account_id) {
                // You could also call: $store->createAsStripeAccount('standard', $data);
                $account = $stripe->accounts->create([
                    'type'  => 'standard', // or 'express' / 'custom' as needed
                    'email' => $store->email,
                    'business_profile' => [
                        'name' => $store->name,
                    ],
                ]);

                $store->stripe_account_id = $account->id;
                $store->save();
            } else {
                // Update existing Stripe account
                $stripe->accounts->update($store->stripe_account_id, [
                    'email' => $store->email,
                    'business_profile' => [
                        'name' => $store->name,
                    ],
                ]);
            }
        } catch (Throwable $e) {
            // Log or handle as you like; avoiding hard failures in Filament forms is usually preferable
            report($e);
        }
    }
}
