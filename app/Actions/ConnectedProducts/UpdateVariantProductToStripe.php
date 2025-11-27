<?php

namespace App\Actions\ConnectedProducts;

use App\Models\ProductVariant;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class UpdateVariantProductToStripe
{
    public function __invoke(ProductVariant $variant): void
    {
        if (!$variant->stripe_product_id || !$variant->stripe_account_id) {
            return;
        }

        $store = Store::where('stripe_account_id', $variant->stripe_account_id)->first();
        if (!$store || !$store->hasStripeAccount()) {
            return;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (!$secret) {
            return;
        }

        $stripe = new StripeClient($secret);

        try {
            $updateData = [];

            // Update name (full title)
            $updateData['name'] = $variant->full_title;

            // Update active status
            $updateData['active'] = $variant->active ?? true;

            // Update shipping
            if ($variant->requires_shipping !== null) {
                $updateData['shippable'] = $variant->requires_shipping;
            }

            // Update metadata
            $product = $variant->product;
            $metadata = [
                'source' => 'variant',
                'parent_product_id' => (string) $product->id,
                'parent_stripe_product_id' => $product->stripe_product_id ?? null,
                'variant_id' => (string) $variant->id,
            ];

            // Add variant options
            if ($variant->option1_name && $variant->option1_value) {
                $metadata['option1_name'] = $variant->option1_name;
                $metadata['option1_value'] = $variant->option1_value;
            }
            if ($variant->option2_name && $variant->option2_value) {
                $metadata['option2_name'] = $variant->option2_name;
                $metadata['option2_value'] = $variant->option2_value;
            }
            if ($variant->option3_name && $variant->option3_value) {
                $metadata['option3_name'] = $variant->option3_name;
                $metadata['option3_value'] = $variant->option3_value;
            }

            // Add SKU and barcode
            if ($variant->sku) {
                $metadata['sku'] = $variant->sku;
            }
            if ($variant->barcode) {
                $metadata['barcode'] = $variant->barcode;
            }

            $updateData['metadata'] = $metadata;

            // Update images if variant has specific image
            if ($variant->image_url) {
                $updateData['images'] = [$variant->image_url];
            } elseif ($product->hasMedia('images')) {
                $updateData['images'] = $product->getStripeImageUrls();
            }

            // Update product in Stripe
            $stripe->products->update(
                $variant->stripe_product_id,
                $updateData,
                ['stripe_account' => $variant->stripe_account_id]
            );

            Log::info('Updated variant product in Stripe', [
                'variant_id' => $variant->id,
                'stripe_product_id' => $variant->stripe_product_id,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to update variant product in Stripe', [
                'variant_id' => $variant->id,
                'stripe_product_id' => $variant->stripe_product_id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }
}

