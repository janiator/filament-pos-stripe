<?php

namespace App\Actions\Webhooks;

use App\Models\ConnectedPaymentLink;
use App\Models\Store;
use Stripe\PaymentLink;

class HandlePaymentLinkWebhook
{
    public function handle(PaymentLink $paymentLink, string $eventType, ?string $accountId = null): void
    {
        if (!$accountId) {
            \Log::warning('Payment link webhook received but no account ID provided', [
                'payment_link_id' => $paymentLink->id,
            ]);
            return;
        }
        
        $store = Store::where('stripe_account_id', $accountId)->first();
        
        if (!$store) {
            \Log::warning('Payment link webhook received but store not found', [
                'payment_link_id' => $paymentLink->id,
                'account_id' => $accountId,
            ]);
            return;
        }

        $data = [
            'stripe_payment_link_id' => $paymentLink->id,
            'stripe_account_id' => $store->stripe_account_id,
            'active' => $paymentLink->active,
            'url' => $paymentLink->url,
            'metadata' => $paymentLink->metadata ? (array) $paymentLink->metadata : null,
        ];

        ConnectedPaymentLink::updateOrCreate(
            [
                'stripe_payment_link_id' => $paymentLink->id,
                'stripe_account_id' => $store->stripe_account_id,
            ],
            $data
        );
    }
}

