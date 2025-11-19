<?php

namespace App\Actions\ConnectedSubscriptions;

use App\Models\ConnectedSubscription;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;

class UpdateConnectedSubscriptionToStripe
{
    public function __invoke(ConnectedSubscription $subscription): void
    {
        if (! $subscription->stripe_id || ! $subscription->stripe_account_id) {
            return;
        }

        $store = Store::where('stripe_account_id', $subscription->stripe_account_id)->first();
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

            // Add syncable fields (listener already checked for changes)
            $updateData['cancel_at_period_end'] = $subscription->cancel_at_period_end ?? false;

            if ($subscription->metadata !== null) {
                $updateData['metadata'] = $subscription->metadata ?? [];
            }

            // Note: Most subscription fields (status, price, quantity) are managed by Stripe
            // and shouldn't be updated directly. Only allow cancel_at_period_end and metadata.

            if (! empty($updateData)) {
                $stripe->subscriptions->update(
                    $subscription->stripe_id,
                    $updateData,
                    ['stripe_account' => $subscription->stripe_account_id]
                );
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}

