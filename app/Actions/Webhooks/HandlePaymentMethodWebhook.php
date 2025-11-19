<?php

namespace App\Actions\Webhooks;

use App\Models\ConnectedPaymentMethod;
use App\Models\Store;
use Stripe\PaymentMethod;

class HandlePaymentMethodWebhook
{
    public function handle(PaymentMethod $paymentMethod, string $eventType, ?string $accountId = null): void
    {
        if (!$accountId) {
            \Log::warning('Payment method webhook received but no account ID provided', [
                'payment_method_id' => $paymentMethod->id,
            ]);
            return;
        }
        
        $store = Store::where('stripe_account_id', $accountId)->first();
        
        if (!$store) {
            \Log::warning('Payment method webhook received but store not found', [
                'payment_method_id' => $paymentMethod->id,
                'account_id' => $accountId,
            ]);
            return;
        }

        $data = [
            'stripe_payment_method_id' => $paymentMethod->id,
            'stripe_account_id' => $store->stripe_account_id,
            'stripe_customer_id' => $paymentMethod->customer ?? null,
            'type' => $paymentMethod->type,
            'card_brand' => $paymentMethod->card->brand ?? null,
            'card_last4' => $paymentMethod->card->last4 ?? null,
            'card_exp_month' => $paymentMethod->card->exp_month ?? null,
            'card_exp_year' => $paymentMethod->card->exp_year ?? null,
            'metadata' => $paymentMethod->metadata ? (array) $paymentMethod->metadata : null,
        ];

        ConnectedPaymentMethod::updateOrCreate(
            [
                'stripe_payment_method_id' => $paymentMethod->id,
                'stripe_account_id' => $store->stripe_account_id,
            ],
            $data
        );
    }
}

