<?php

namespace App\Actions\Webhooks;

use App\Models\ConnectedProduct;
use App\Models\Store;
use Stripe\Product;

class HandleProductWebhook
{
    public function handle(Product $product, string $eventType, ?string $accountId = null): void
    {
        if (!$accountId) {
            \Log::warning('Product webhook received but no account ID provided', [
                'product_id' => $product->id,
            ]);
            return;
        }
        
        $store = Store::where('stripe_account_id', $accountId)->first();
        
        if (!$store) {
            \Log::warning('Product webhook received but store not found', [
                'product_id' => $product->id,
                'account_id' => $accountId,
            ]);
            return;
        }

        $data = [
            'stripe_product_id' => $product->id,
            'stripe_account_id' => $store->stripe_account_id,
            'name' => $product->name,
            'description' => $product->description,
            'active' => $product->active,
            'images' => $product->images ? (array) $product->images : null,
            'metadata' => $product->metadata ? (array) $product->metadata : null,
            'type' => $product->type ?? 'service',
            'url' => $product->url ?? null,
        ];

        ConnectedProduct::updateOrCreate(
            [
                'stripe_product_id' => $product->id,
                'stripe_account_id' => $store->stripe_account_id,
            ],
            $data
        );
    }
}

