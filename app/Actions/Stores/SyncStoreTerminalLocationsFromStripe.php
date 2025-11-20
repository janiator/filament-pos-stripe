<?php

namespace App\Actions\Stores;

use App\Models\Store;
use App\Models\TerminalLocation;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;

class SyncStoreTerminalLocationsFromStripe
{
    public function __invoke(Store $store): array
    {
        $result = [
            'total'   => 0,
            'created' => 0,
            'updated' => 0,
            'error'   => null,
        ];

        try {
            if (! $store->hasStripeAccount()) {
                $result['error'] = 'Store has no Stripe account mapping (hasStripeAccount() is false).';
                return $result;
            }

            // Get locations from CONNECTED ACCOUNT
            $stripeLocations = $store->getTerminalLocations([
                'limit' => 100,
            ], true);

            $items = $stripeLocations->data ?? $stripeLocations ?? [];

            foreach ($items as $location) {
                $result['total']++;

                // Use updateOrCreate with stripe_location_id as the unique key
                // since it has a unique constraint in the database
                $record = TerminalLocation::updateOrCreate(
                    [
                        'stripe_location_id' => $location->id,
                    ],
                    [
                        'store_id'     => $store->id,
                        'display_name' => $location->display_name ?? $location->id,
                        'line1'        => $location->address->line1 ?? '',
                        'line2'        => $location->address->line2 ?? null,
                        'city'         => $location->address->city ?? '',
                        'state'        => $location->address->state ?? null,
                        'postal_code'  => $location->address->postal_code ?? '',
                        'country'      => $location->address->country ?? '',
                    ]
                );

                if ($record->wasRecentlyCreated) {
                    $result['created']++;
                } else {
                    $result['updated']++;
                }
            }
        } catch (AccountNotFoundException $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }
}
