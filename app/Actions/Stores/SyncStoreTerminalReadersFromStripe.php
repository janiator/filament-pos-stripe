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

                // Use updateOrCreate with stripe_reader_id as the unique key
                // since it has a unique constraint in the database
                $deviceType = $reader->device_type ?? '';
                
                $record = TerminalReader::updateOrCreate(
                    [
                        'stripe_reader_id' => $reader->id,
                    ],
                    [
                        'store_id'            => $store->id,
                        'label'               => $reader->label ?? $reader->id,
                        'terminal_location_id' => $terminalLocationId,
                        'device_type'         => $reader->device_type ?? null,
                        'status'              => $reader->status ?? null,
                        'tap_to_pay'          => str_contains($deviceType, 'tap_to_pay'),
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
