<?php

namespace App\Actions\TerminalLocations;

use App\Models\TerminalLocation;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;

class UpdateTerminalLocationToStripe
{
    public function __invoke(TerminalLocation $location): void
    {
        if (! $location->stripe_location_id) {
            return;
        }

        $store = $location->store;
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

            // Check which fields changed
            if ($location->getOriginal('display_name') !== $location->display_name) {
                $updateData['display_name'] = $location->display_name;
            }

            // Build address if any address fields changed
            $addressFields = ['line1', 'line2', 'city', 'state', 'postal_code', 'country'];
            $addressChanged = false;
            $address = [];

            foreach ($addressFields as $field) {
                $original = $location->getOriginal($field);
                $current = $location->getAttribute($field);
                if ($original !== $current) {
                    $addressChanged = true;
                }
                if ($current) {
                    $address[$field] = $current;
                }
            }

            if ($addressChanged && !empty($address)) {
                $updateData['address'] = $address;
            }

            if (! empty($updateData)) {
                $stripe->terminal->locations->update(
                    $location->stripe_location_id,
                    $updateData,
                    ['stripe_account' => $store->stripe_account_id]
                );
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}

