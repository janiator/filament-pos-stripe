<?php

namespace App\Actions\ConnectedProducts;

use App\Models\ProductVariant;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class CreateVariantProductInStripe
{
    public function __invoke(ProductVariant $variant): ?string
    {
        if (!$variant->stripe_account_id) {
            Log::warning('Cannot create variant product in Stripe: missing stripe_account_id', [
                'variant_id' => $variant->id,
            ]);
            return null;
        }

        $store = Store::where('stripe_account_id', $variant->stripe_account_id)->first();
        if (!$store || !$store->hasStripeAccount()) {
            Log::warning('Cannot create variant product in Stripe: store not found or invalid', [
                'variant_id' => $variant->id,
                'stripe_account_id' => $variant->stripe_account_id,
            ]);
            return null;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (!$secret) {
            Log::warning('Cannot create variant product in Stripe: Stripe secret not configured');
            return null;
        }

        $stripe = new StripeClient($secret);

        try {
            $product = $variant->product;
            
            // Build variant name: "Product Name - Variant Options"
            $variantName = $variant->full_title;

            $createData = [
                'name' => $variantName,
                'type' => $product->type ?? 'good', // Variants are typically physical goods
            ];

            // Add product description (can be same as parent or variant-specific)
            if ($product->description !== null) {
                $createData['description'] = $product->description;
            }

            // Set active status
            $createData['active'] = $variant->active ?? true;

            // Shipping settings from variant
            if ($variant->requires_shipping !== null) {
                $createData['shippable'] = $variant->requires_shipping;
            } elseif ($product->shippable !== null) {
                $createData['shippable'] = $product->shippable;
            }

            // Package dimensions (could be variant-specific, but using product for now)
            if ($product->package_dimensions !== null && is_array($product->package_dimensions)) {
                $createData['package_dimensions'] = $product->package_dimensions;
            }

            // Build metadata with variant and parent product info
            $metadata = [
                'source' => 'variant',
                'parent_product_id' => (string) $product->id,
                'variant_id' => (string) $variant->id,
            ];

            // Add variant options to metadata
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

            // Add parent product metadata if available
            if ($product->product_meta !== null && is_array($product->product_meta)) {
                foreach ($product->product_meta as $key => $value) {
                    if (!is_string($key) || 
                        str_contains($key, "\0") || 
                        str_contains($key, '*') || 
                        str_contains($key, '_opts') ||
                        str_starts_with($key, '_')) {
                        continue;
                    }
                    
                    // Prefix parent metadata to avoid conflicts
                    $metadataKey = 'parent_' . $key;
                    if (is_string($value)) {
                        $metadata[$metadataKey] = $value;
                    } elseif (is_scalar($value)) {
                        $metadata[$metadataKey] = (string) $value;
                    } elseif (!is_null($value)) {
                        $metadata[$metadataKey] = json_encode($value);
                    }
                }
            }

            // Add variant-specific metadata
            if ($variant->metadata !== null && is_array($variant->metadata)) {
                foreach ($variant->metadata as $key => $value) {
                    if (!is_string($key) || 
                        str_contains($key, "\0") || 
                        str_contains($key, '*') || 
                        str_contains($key, '_opts') ||
                        str_starts_with($key, '_')) {
                        continue;
                    }
                    
                    if (is_string($value)) {
                        $metadata[$key] = $value;
                    } elseif (is_scalar($value)) {
                        $metadata[$key] = (string) $value;
                    } elseif (!is_null($value)) {
                        $metadata[$key] = json_encode($value);
                    }
                }
            }

            $createData['metadata'] = $metadata;

            // Add images if variant has specific image
            $images = [];
            if ($variant->image_url) {
                $images[] = $variant->image_url;
            } elseif ($product && $product->hasMedia('images')) {
                // Use parent product images
                $images = $product->getStripeImageUrls();
            } elseif ($product && $product->images && is_array($product->images)) {
                $images = $product->images;
            }

            if (!empty($images)) {
                $createData['images'] = $images;
            }

            // Create product in Stripe
            $stripeProduct = $stripe->products->create(
                $createData,
                ['stripe_account' => $variant->stripe_account_id]
            );

            Log::info('Created variant product in Stripe', [
                'variant_id' => $variant->id,
                'stripe_product_id' => $stripeProduct->id,
                'stripe_account_id' => $variant->stripe_account_id,
                'variant_name' => $variantName,
            ]);

            return $stripeProduct->id;
        } catch (Throwable $e) {
            Log::error('Failed to create variant product in Stripe', [
                'variant_id' => $variant->id,
                'stripe_account_id' => $variant->stripe_account_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            report($e);
            return null;
        }
    }
}

