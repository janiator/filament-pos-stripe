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
            // Log available stores for debugging
            $availableStores = Store::whereNotNull('stripe_account_id')
                ->pluck('stripe_account_id', 'id')
                ->toArray();
            
            \Log::warning('Product webhook received but store not found', [
                'product_id' => $product->id,
                'account_id' => $accountId,
                'available_stores' => array_keys($availableStores),
                'available_account_ids' => array_values($availableStores),
            ]);
            return;
        }
        
        \Log::info('Processing product webhook', [
            'product_id' => $product->id,
            'account_id' => $accountId,
            'store_id' => $store->id,
            'event_type' => $eventType,
        ]);

        if ($eventType === 'product.deleted') {
            ConnectedProduct::where('stripe_product_id', $product->id)
                ->where('stripe_account_id', $store->stripe_account_id)
                ->update(['active' => false]);
            \Log::info('Product webhook processed (deleted)', ['product_id' => $product->id]);

            return;
        }

        $data = [
            'stripe_product_id' => $product->id,
            'stripe_account_id' => $store->stripe_account_id,
            'name' => $product->name,
            'description' => $product->description,
            'active' => $product->active,
            'images' => $product->images ? (array) $product->images : null,
            'product_meta' => $product->metadata ? (array) $product->metadata : null,
            'type' => $product->type ?? 'service',
            'url' => $product->url ?? null,
            'package_dimensions' => $product->package_dimensions ? (array) $product->package_dimensions : null,
            'shippable' => isset($product->shippable) ? (bool) $product->shippable : null,
            'statement_descriptor' => $product->statement_descriptor ?? null,
            'tax_code' => $product->tax_code ?? null,
            'unit_label' => $product->unit_label ?? null,
            'default_price' => $product->default_price ?? null,
        ];

        $productRecord =         // Use withoutEvents to prevent triggering sync back to Stripe when syncing FROM Stripe
        ConnectedProduct::withoutEvents(function () use ($product, $store, $data) {
            return ConnectedProduct::updateOrCreate(
                [
                    'stripe_product_id' => $product->id,
                    'stripe_account_id' => $store->stripe_account_id,
                ],
                $data
            );
        });
        
        \Log::info('Product webhook processed successfully', [
            'product_id' => $product->id,
            'product_record_id' => $productRecord->id,
            'was_created' => $productRecord->wasRecentlyCreated,
        ]);
    }
}

