<?php

namespace App\Filament\Resources\ConnectedCharges\Pages;

use App\Actions\ConnectedCharges\CreateConnectedChargeOnStripe;
use App\Filament\Resources\ConnectedCharges\ConnectedChargeResource;
use App\Models\Store;
use Filament\Resources\Pages\CreateRecord;

class CreateConnectedCharge extends CreateRecord
{
    protected static string $resource = ConnectedChargeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $store = Store::where('stripe_account_id', $data['stripe_account_id'])->firstOrFail();
        $action = new CreateConnectedChargeOnStripe();
        $charge = $action($store, $data, true);

        if (! $charge) {
            throw new \Exception('Failed to create charge on Stripe.');
        }

        return [
            'stripe_charge_id' => $charge->stripe_charge_id,
            'stripe_account_id' => $charge->stripe_account_id,
            'stripe_customer_id' => $charge->stripe_customer_id,
            'stripe_payment_intent_id' => $charge->stripe_payment_intent_id,
            'amount' => $charge->amount,
            'amount_refunded' => $charge->amount_refunded,
            'currency' => $charge->currency,
            'status' => $charge->status,
            'payment_method' => $charge->payment_method,
            'description' => $charge->description,
            'failure_code' => $charge->failure_code,
            'failure_message' => $charge->failure_message,
            'captured' => $charge->captured,
            'refunded' => $charge->refunded,
            'paid' => $charge->paid,
            'paid_at' => $charge->paid_at,
            'metadata' => $charge->metadata,
            'outcome' => $charge->outcome,
            'charge_type' => $charge->charge_type,
            'application_fee_amount' => $charge->application_fee_amount,
        ];
    }
}
