<?php

namespace App\Actions\ConnectedProducts;

use App\Models\ConnectedPrice;
use App\Models\ConnectedProduct;
use App\Models\Store;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class SyncConnectedProductsFromStripe
{
    public function __invoke(Store $store, bool $notify = false): array
    {
        $result = [
            'total'   => 0,
            'created' => 0,
            'updated' => 0,
            'errors'  => [],
        ];

        try {
            // Refresh store FIRST to get the latest stripe_account_id
            $store->refresh();
            $stripeAccountId = $store->stripe_account_id;
            
            if (! $store->hasStripeAccount() || empty($stripeAccountId)) {
                if ($notify) {
                    Notification::make()
                        ->title('Store not connected')
                        ->body('This store is not connected to Stripe.')
                        ->danger()
                        ->send();
                }

                return $result;
            }

            $secret = config('cashier.secret') ?? config('services.stripe.secret');

            if (! $secret) {
                if ($notify) {
                    Notification::make()
                        ->title('Stripe not configured')
                        ->body('No Stripe secret key found.')
                        ->danger()
                        ->send();
                }

                return $result;
            }

            $stripe = new StripeClient($secret);

            // Get products from the connected account
            // Only fetch active products (exclude archived products)
            $products = $stripe->products->all(
                [
                    'limit' => 100,
                    'active' => true, // Only sync active products, skip archived ones
                ],
                ['stripe_account' => $stripeAccountId]
            );

            // First pass: collect variant products that need parent products
            $pendingVariants = [];

            foreach ($products->autoPagingIterator() as $product) {
                $result['total']++;

                try {
                    // Ensure stripe_account_id is still valid (double-check)
                    if (empty($stripeAccountId)) {
                        $result['errors'][] = "Product {$product->id}: stripe_account_id is empty";
                        continue;
                    }

                    // Check if this is a variant product (has variant_id in metadata)
                    $metadata = $product->metadata ? (array) $product->metadata : [];
                    $isVariant = isset($metadata['variant_id']) || 
                                 (isset($metadata['source']) && in_array($metadata['source'], ['pos-variant', 'variant']));

                    if ($isVariant) {
                        // This is a variant product - try to sync, but store for second pass if parent not found
                        $variantSynced = $this->syncVariantProduct($product, $store, $stripeAccountId, $result);
                        if (!$variantSynced) {
                            // Store for second pass
                            $pendingVariants[] = $product;
                        }
                        continue;
                    }

                    $data = [
                        'stripe_product_id' => $product->id,
                        'stripe_account_id' => $stripeAccountId, // Use the refreshed value
                        'name' => $product->name ?? $product->id, // Use product ID as fallback if name is null
                        'description' => $product->description,
                        'active' => $product->active,
                        'images' => $product->images ? (array) $product->images : null,
                        'product_meta' => $metadata,
                        'type' => $product->type ?? 'service',
                        'url' => $product->url ?? null,
                        'package_dimensions' => $product->package_dimensions ? (array) $product->package_dimensions : null,
                        'shippable' => isset($product->shippable) ? (bool) $product->shippable : null,
                        'statement_descriptor' => $product->statement_descriptor ?? null,
                        'tax_code' => $product->tax_code ?? null,
                        'unit_label' => $product->unit_label ?? null,
                        'default_price' => $product->default_price ?? null,
                    ];

                    // Double-check stripe_account_id is not null before creating
                    if (empty($data['stripe_account_id'])) {
                        $result['errors'][] = "Product {$product->id}: stripe_account_id is null after data preparation";
                        continue;
                    }

                    // Find by stripe_product_id only (since it's unique)
                    // The same product might exist with a different stripe_account_id
                    $productRecord = ConnectedProduct::where('stripe_product_id', $product->id)->first();

                    if ($productRecord) {
                        // Update existing record - ensure stripe_account_id is set correctly
                        $productRecord->fill($data);
                        // Explicitly set stripe_account_id to ensure it's updated if it changed
                        $productRecord->stripe_account_id = $stripeAccountId;
                        // Use saveQuietly to prevent triggering sync back to Stripe
                        $productRecord->saveQuietly();
                        $result['updated']++;
                    } else {
                        // Create new record without triggering events
                        ConnectedProduct::withoutEvents(function () use ($data) {
                            ConnectedProduct::create($data);
                        });
                        $result['created']++;
                    }

                    // Also sync prices for this product
                    if (isset($product->default_price)) {
                        try {
                            $price = $stripe->prices->retrieve(
                                $product->default_price,
                                [],
                                ['stripe_account' => $stripeAccountId]
                            );
                            $this->syncPrice($price, $store, $stripeAccountId);
                        } catch (Throwable $e) {
                            // Log but don't fail the product sync
                            report($e);
                        }
                    }

                    // Get all prices for this product
                    $prices = $stripe->prices->all(
                        ['product' => $product->id, 'limit' => 100],
                        ['stripe_account' => $stripeAccountId]
                    );

                    foreach ($prices->autoPagingIterator() as $price) {
                        try {
                            $this->syncPrice($price, $store, $stripeAccountId);
                        } catch (Throwable $e) {
                            $result['errors'][] = "Price {$price->id}: {$e->getMessage()}";
                            report($e);
                        }
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Product {$product->id}: {$e->getMessage()}";
                    report($e);
                }
            }

            // Second pass: retry syncing variants that were pending (parent products should now exist)
            foreach ($pendingVariants as $product) {
                try {
                    $this->syncVariantProduct($product, $store, $stripeAccountId, $result);
                } catch (Throwable $e) {
                    $result['errors'][] = "Variant product {$product->id} (retry): {$e->getMessage()}";
                    report($e);
                }
            }

            if ($notify) {
                if (! empty($result['errors'])) {
                    $errorDetails = implode("\n", array_slice($result['errors'], 0, 5));
                    if (count($result['errors']) > 5) {
                        $errorDetails .= "\n... and " . (count($result['errors']) - 5) . " more error(s)";
                    }
                    Notification::make()
                        ->title('Sync completed with errors')
                        ->body("Found {$result['total']} products. {$result['created']} created, {$result['updated']} updated.\n\nErrors:\n{$errorDetails}")
                        ->warning()
                        ->persistent()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Products synced')
                        ->body("Found {$result['total']} products. {$result['created']} created, {$result['updated']} updated.")
                        ->success()
                        ->send();
                }
            }

            return $result;
        } catch (AccountNotFoundException $e) {
            if ($notify) {
                Notification::make()
                    ->title('Sync failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            return $result;
        } catch (Throwable $e) {
            if ($notify) {
                Notification::make()
                    ->title('Sync failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            report($e);
            return $result;
        }
    }

    protected function syncPrice($price, Store $store, ?string $stripeAccountId = null): void
    {
        // Use provided stripeAccountId or refresh from store
        if (empty($stripeAccountId)) {
            $store->refresh();
            $stripeAccountId = $store->stripe_account_id;
        }
        
        if (empty($stripeAccountId)) {
            \Log::warning('Cannot sync price: store missing stripe_account_id', [
                'store_id' => $store->id,
                'price_id' => $price->id,
            ]);
            return;
        }

        $data = [
            'stripe_price_id' => $price->id,
            'stripe_product_id' => $price->product,
            'stripe_account_id' => $stripeAccountId,
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

        // Find by stripe_price_id only (since it's unique)
        // The same price might exist with a different stripe_account_id
        $priceRecord = ConnectedPrice::where('stripe_price_id', $price->id)->first();

        if ($priceRecord) {
            // Update existing record - ensure stripe_account_id is set correctly
            $priceRecord->fill($data);
            // Explicitly set stripe_account_id to ensure it's updated if it changed
            $priceRecord->stripe_account_id = $stripeAccountId;
            // Use saveQuietly to prevent triggering events
            $priceRecord->saveQuietly();
        } else {
            // Create new record without triggering events
            ConnectedPrice::withoutEvents(function () use ($data) {
                ConnectedPrice::create($data);
            });
        }
    }

    /**
     * Sync a variant product from Stripe to ProductVariant table
     * 
     * @return bool True if variant was synced successfully, false if parent product not found yet
     */
    protected function syncVariantProduct($product, Store $store, string $stripeAccountId, array &$result): bool
    {
        $metadata = $product->metadata ? (array) $product->metadata : [];
        $parentProductId = $metadata['parent_product_id'] ?? null;
        $parentStripeProductId = $metadata['parent_stripe_product_id'] ?? null;
        $variantId = $metadata['variant_id'] ?? null;

        // Find the parent product - try by database ID first, then by Stripe product ID
        $parentProduct = null;
        
        if ($parentProductId) {
            // Try to find by database ID
            $parentProduct = ConnectedProduct::where('id', $parentProductId)
                ->where('stripe_account_id', $stripeAccountId)
                ->first();
        }
        
        // If not found by ID, try by Stripe product ID
        if (!$parentProduct && $parentStripeProductId) {
            $parentProduct = ConnectedProduct::where('stripe_product_id', $parentStripeProductId)
                ->where('stripe_account_id', $stripeAccountId)
                ->first();
        }

        if (!$parentProduct) {
            // Parent product doesn't exist yet - return false so we can retry later
            Log::debug('Parent product not found for variant', [
                'variant_stripe_product_id' => $product->id,
                'parent_product_id' => $parentProductId,
                'parent_stripe_product_id' => $parentStripeProductId,
                'stripe_account_id' => $stripeAccountId,
            ]);
            return false;
        }

        // Extract variant options from metadata
        $option1Name = $metadata['option1_name'] ?? null;
        $option1Value = $metadata['option1_value'] ?? null;
        $option2Name = $metadata['option2_name'] ?? null;
        $option2Value = $metadata['option2_value'] ?? null;
        $option3Name = $metadata['option3_name'] ?? null;
        $option3Value = $metadata['option3_value'] ?? null;

        // Get price for this variant
        $priceId = $product->default_price;
        $priceAmount = null;
        $currency = 'nok';
        $price = null;
        if ($priceId) {
            try {
                $stripe = new \Stripe\StripeClient(config('cashier.secret') ?? config('services.stripe.secret'));
                $price = $stripe->prices->retrieve($priceId, [], ['stripe_account' => $stripeAccountId]);
                $priceAmount = $price->unit_amount;
                $currency = $price->currency ?? 'nok';
            } catch (\Throwable $e) {
                \Log::warning('Failed to retrieve variant price', [
                    'variant_product_id' => $product->id,
                    'price_id' => $priceId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Find or create variant
        $variant = \App\Models\ProductVariant::where('stripe_product_id', $product->id)
            ->where('stripe_account_id', $stripeAccountId)
            ->first();

        $variantData = [
            'connected_product_id' => $parentProduct->id,
            'stripe_account_id' => $stripeAccountId,
            'stripe_product_id' => $product->id,
            'stripe_price_id' => $priceId,
            'option1_name' => $option1Name,
            'option1_value' => $option1Value,
            'option2_name' => $option2Name,
            'option2_value' => $option2Value,
            'option3_name' => $option3Name,
            'option3_value' => $option3Value,
            'sku' => $metadata['sku'] ?? null,
            'barcode' => $metadata['barcode'] ?? null,
            'price_amount' => $priceAmount,
            'currency' => $currency,
            'requires_shipping' => isset($product->shippable) ? (bool) $product->shippable : true,
            'taxable' => true,
            'active' => $product->active,
            'image_url' => !empty($product->images) ? $product->images[0] : null,
            'metadata' => $metadata,
        ];

        if ($variant) {
            $variant->fill($variantData);
            // Use saveQuietly to prevent triggering sync back to Stripe
            $variant->saveQuietly();
            $result['updated']++;
        } else {
            // Create new record without triggering events
            \App\Models\ProductVariant::withoutEvents(function () use ($variantData) {
                \App\Models\ProductVariant::create($variantData);
            });
            $result['created']++;
        }

        // Sync the price if we retrieved it
        if ($priceId && isset($price)) {
            $this->syncPrice($price, $store, $stripeAccountId);
        }

        return true;
    }
}
