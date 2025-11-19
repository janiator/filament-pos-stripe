<?php

namespace App\Actions\Webhooks;

use App\Models\ConnectedCharge;
use App\Models\Store;
use Stripe\Charge;

class HandleChargeWebhook
{
    public function handle(Charge $charge, string $eventType): void
    {
        // Find the store by stripe_account_id from the charge
        $accountId = $charge->on_behalf_of ?? $charge->destination;
        
        if (!$accountId) {
            \Log::warning('Charge webhook received but no account ID found', [
                'charge_id' => $charge->id,
            ]);
            return;
        }
        
        $store = Store::where('stripe_account_id', $accountId)->first();
        
        if (!$store) {
            \Log::warning('Charge webhook received but store not found', [
                'charge_id' => $charge->id,
                'account_id' => $accountId,
            ]);
            return;
        }

        // Sync the specific charge
        $data = [
            'stripe_charge_id' => $charge->id,
            'stripe_account_id' => $store->stripe_account_id,
            'stripe_customer_id' => $charge->customer ?? null,
            'stripe_payment_intent_id' => $charge->payment_intent ?? null,
            'amount' => $charge->amount,
            'amount_refunded' => $charge->amount_refunded ?? 0,
            'currency' => $charge->currency,
            'status' => $charge->status,
            'payment_method' => $charge->payment_method_details?->type ?? null,
            'description' => $charge->description,
            'failure_code' => $charge->failure_code,
            'failure_message' => $charge->failure_message,
            'captured' => $charge->captured ?? true,
            'refunded' => $charge->refunded ?? false,
            'paid' => $charge->paid ?? false,
            'paid_at' => $charge->created ? date('Y-m-d H:i:s', $charge->created) : null,
            'metadata' => $charge->metadata ? (array) $charge->metadata : null,
            'outcome' => $charge->outcome ? (array) $charge->outcome : null,
            'charge_type' => $charge->on_behalf_of ? 'destination' : 'direct',
            'application_fee_amount' => $charge->application_fee_amount ?? null,
        ];

        ConnectedCharge::updateOrCreate(
            [
                'stripe_charge_id' => $charge->id,
                'stripe_account_id' => $store->stripe_account_id,
            ],
            $data
        );
    }
}

