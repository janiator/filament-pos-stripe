<?php

namespace App\Actions\Webhooks;

use App\Models\ConnectedPrice;
use App\Models\Store;
use Stripe\Price;

class HandlePriceWebhook
{
    public function handle(Price $price, string $eventType, ?string $accountId = null): void
    {
        if (!$accountId) {
            \Log::warning('Price webhook received but no account ID provided', [
                'price_id' => $price->id,
            ]);
            return;
        }
        
        $store = Store::where('stripe_account_id', $accountId)->first();
        
        if (!$store) {
            \Log::warning('Price webhook received but store not found', [
                'price_id' => $price->id,
                'account_id' => $accountId,
            ]);
            return;
        }

        if ($eventType === 'price.deleted') {
            ConnectedPrice::where('stripe_price_id', $price->id)
                ->where('stripe_account_id', $store->stripe_account_id)
                ->update(['active' => false]);
            \Log::info('Price webhook processed (deleted)', ['price_id' => $price->id]);

            return;
        }

        $data = [
            'stripe_price_id' => $price->id,
            'stripe_product_id' => $price->product,
            'stripe_account_id' => $store->stripe_account_id,
            'active' => $price->active,
            'currency' => $price->currency,
            'unit_amount' => $price->unit_amount,
            'billing_scheme' => $price->billing_scheme ?? null,
            'type' => $price->type,
            'recurring_interval' => $price->recurring->interval ?? null,
            'recurring_interval_count' => $price->recurring->interval_count ?? null,
            'recurring_usage_type' => $price->recurring->usage_type ?? null,
            'metadata' => $price->metadata ? (array) $price->metadata : null,
        ];

        ConnectedPrice::updateOrCreate(
            [
                'stripe_price_id' => $price->id,
                'stripe_account_id' => $store->stripe_account_id,
            ],
            $data
        );
    }
}

