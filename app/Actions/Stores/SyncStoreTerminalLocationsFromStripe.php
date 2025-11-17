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

                $record = TerminalLocation::firstOrNew([
                    'store_id'           => $store->id,
                    'stripe_location_id' => $location->id,
                ]);

                $record->display_name = $location->display_name ?? $location->id;
                $record->line1        = $location->address->line1 ?? '';
                $record->line2        = $location->address->line2 ?? null;
                $record->city         = $location->address->city ?? '';
                $record->state        = $location->address->state ?? null;
                $record->postal_code  = $location->address->postal_code ?? '';
                $record->country      = $location->address->country ?? '';

                if ($record->exists) {
                    $result['updated']++;
                } else {
                    $result['created']++;
                }

                $record->save();
            }
        } catch (AccountNotFoundException $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }
}
