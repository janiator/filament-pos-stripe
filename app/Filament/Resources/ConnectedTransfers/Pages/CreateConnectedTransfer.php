<?php

namespace App\Filament\Resources\ConnectedTransfers\Pages;

use App\Actions\ConnectedTransfers\CreateConnectedTransferOnStripe;
use App\Filament\Resources\ConnectedTransfers\ConnectedTransferResource;
use App\Models\Store;
use Filament\Resources\Pages\CreateRecord;

class CreateConnectedTransfer extends CreateRecord
{
    protected static string $resource = ConnectedTransferResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $store = Store::where('stripe_account_id', $data['stripe_account_id'])->firstOrFail();
        $action = new CreateConnectedTransferOnStripe();
        $transfer = $action($store, $data, true);

        if (! $transfer) {
            throw new \Exception('Failed to create transfer on Stripe.');
        }

        return [
            'stripe_transfer_id' => $transfer->stripe_transfer_id,
            'stripe_account_id' => $transfer->stripe_account_id,
            'stripe_charge_id' => $transfer->stripe_charge_id,
            'stripe_payment_intent_id' => $transfer->stripe_payment_intent_id,
            'amount' => $transfer->amount,
            'currency' => $transfer->currency,
            'status' => $transfer->status,
            'destination' => $transfer->destination,
            'description' => $transfer->description,
            'arrival_date' => $transfer->arrival_date,
            'metadata' => $transfer->metadata,
            'reversals' => $transfer->reversals,
            'reversed_amount' => $transfer->reversed_amount,
        ];
    }
}
