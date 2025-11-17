<?php

namespace App\Actions\Stores;

use App\Models\Store;
use App\Models\TerminalLocation;
use App\Models\TerminalReader;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;

class SyncStoreTerminalReadersFromStripe
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

            $stripeReaders = $store->getTerminalReaders([
                'limit' => 100,
            ], true);

            $items = $stripeReaders->data ?? $stripeReaders ?? [];

            foreach ($items as $reader) {
                $result['total']++;

                $locationId = $reader->location ?? null;

                $terminalLocationId = null;

                if ($locationId) {
                    $terminalLocationId = TerminalLocation::where('store_id', $store->id)
                        ->where('stripe_location_id', $locationId)
                        ->value('id');
                }

                $record = TerminalReader::firstOrNew([
                    'store_id'         => $store->id,
                    'stripe_reader_id' => $reader->id,
                ]);

                $record->label                = $reader->label ?? $reader->id;
                $record->terminal_location_id = $terminalLocationId;
                $record->device_type          = $reader->device_type ?? null;
                $record->status               = $reader->status ?? null;

                $deviceType = $reader->device_type ?? '';
                $record->tap_to_pay = str_contains($deviceType, 'tap_to_pay');

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
