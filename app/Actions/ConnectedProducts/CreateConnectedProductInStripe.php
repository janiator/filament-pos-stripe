<?php

namespace App\Actions\ConnectedProducts;

use App\Models\ConnectedProduct;
use App\Models\Store;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Throwable;

class CreateConnectedProductInStripe
{
    public function __invoke(ConnectedProduct $product): ?string
    {
        if (! $product->stripe_account_id) {
            Log::warning('Cannot create product in Stripe: missing stripe_account_id', [
                'product_id' => $product->id,
            ]);

            return null;
        }

        $store = Store::where('stripe_account_id', $product->stripe_account_id)->first();
        if (! $store || ! $store->hasStripeAccount()) {
            Log::warning('Cannot create product in Stripe: store not found or invalid', [
                'product_id' => $product->id,
                'stripe_account_id' => $product->stripe_account_id,
            ]);

            return null;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (! $secret) {
            Log::warning('Cannot create product in Stripe: Stripe secret not configured');

            return null;
        }

        $stripe = new StripeClient($secret);

        try {
            $createData = [
                'name' => $product->name,
                'type' => $product->type ?? 'service',
            ];

            // Add optional fields if they exist
            if ($product->description !== null) {
                $createData['description'] = $product->description;
            }

            if ($product->active !== null) {
                $createData['active'] = $product->active;
            }

            if ($product->url !== null) {
                $createData['url'] = $product->url;
            }

            if ($product->images !== null && is_array($product->images) && count($product->images) > 0) {
                $createData['images'] = array_values(array_filter(array_slice($product->images, 0, 8), 'is_string'));
            }

            if ($product->shippable !== null) {
                $createData['shippable'] = $product->shippable;
            }

            if ($product->statement_descriptor !== null) {
                $createData['statement_descriptor'] = $product->statement_descriptor;
            }

            if ($product->tax_code !== null) {
                $createData['tax_code'] = $product->tax_code;
            }

            if ($product->unit_label !== null) {
                $createData['unit_label'] = $product->unit_label;
            }

            if ($product->package_dimensions !== null && is_array($product->package_dimensions)) {
                $createData['package_dimensions'] = $product->package_dimensions;
            }

            if ($product->product_meta !== null && is_array($product->product_meta)) {
                // Ensure all metadata values are strings
                $metadata = [];
                foreach ($product->product_meta as $key => $value) {
                    if (! is_string($key) ||
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
                    } elseif (! is_null($value)) {
                        $metadata[$key] = json_encode($value);
                    }
                }

                if (! empty($metadata)) {
                    $createData['metadata'] = $metadata;
                }
            }

            // Create product in Stripe
            $stripeProduct = $stripe->products->create(
                $createData,
                ['stripe_account' => $product->stripe_account_id]
            );

            Log::info('Created product in Stripe', [
                'product_id' => $product->id,
                'stripe_product_id' => $stripeProduct->id,
                'stripe_account_id' => $product->stripe_account_id,
                'product_name' => $product->name,
            ]);

            return $stripeProduct->id;
        } catch (Throwable $e) {
            Log::error('Failed to create product in Stripe', [
                'product_id' => $product->id,
                'stripe_account_id' => $product->stripe_account_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            report($e);

            return null;
        }
    }
}
