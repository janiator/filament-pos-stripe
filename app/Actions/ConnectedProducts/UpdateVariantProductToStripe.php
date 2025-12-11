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
            // Handle price changes: if price_amount is null/0, skip Stripe sync
            // Variants without prices are for custom price input on POS
            if (!$variant->price_amount || $variant->price_amount <= 0) {
                Log::info('Skipping Stripe update for variant - no price set (custom price on POS)', [
                    'variant_id' => $variant->id,
                    'stripe_product_id' => $variant->stripe_product_id,
                ]);
                
                // If price was removed (stripe_price_id exists but price_amount is null), archive the Stripe product
                if ($variant->stripe_price_id) {
                    try {
                        $stripe->products->update(
                            $variant->stripe_product_id,
                            ['active' => false],
                            ['stripe_account' => $variant->stripe_account_id]
                        );
                        Log::info('Archived Stripe product for variant without price', [
                            'variant_id' => $variant->id,
                            'stripe_product_id' => $variant->stripe_product_id,
                        ]);
                        // Clear stripe_price_id since price no longer exists
                        $variant->stripe_price_id = null;
                        $variant->saveQuietly();
                    } catch (Throwable $e) {
                        Log::warning('Failed to archive Stripe product for variant without price', [
                            'variant_id' => $variant->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                return;
            }

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

            // Handle price creation/update if price_amount is set
            // Skip if no_price_in_pos is enabled
            $needsPriceUpdate = false;
            
            // If no_price_in_pos is enabled, archive existing price if it exists
            if ($variant->no_price_in_pos && $variant->stripe_price_id) {
                try {
                    $stripe->prices->update(
                        $variant->stripe_price_id,
                        ['active' => false],
                        ['stripe_account' => $variant->stripe_account_id]
                    );
                    $variant->stripe_price_id = null;
                    $variant->saveQuietly();
                    Log::info('Archived Stripe price - no_price_in_pos enabled', [
                        'variant_id' => $variant->id,
                    ]);
                } catch (Throwable $e) {
                    Log::warning('Failed to archive Stripe price', [
                        'variant_id' => $variant->id,
                        'price_id' => $variant->stripe_price_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } elseif (!$variant->no_price_in_pos && $variant->price_amount && $variant->price_amount > 0) {
                // Check if we need to create/update price
                // If stripe_price_id doesn't exist or price amount might have changed, create new price
                if (!$variant->stripe_price_id) {
                    $needsPriceUpdate = true;
                } else {
                    // Check if current price matches - if not, create new price
                    try {
                        $currentPrice = $stripe->prices->retrieve(
                            $variant->stripe_price_id,
                            [],
                            ['stripe_account' => $variant->stripe_account_id]
                        );
                        if ($currentPrice->unit_amount != $variant->price_amount || 
                            $currentPrice->currency != strtolower($variant->currency ?? 'nok')) {
                            $needsPriceUpdate = true;
                        }
                    } catch (Throwable $e) {
                        // Price doesn't exist or error retrieving, create new one
                        $needsPriceUpdate = true;
                    }
                }
            }

            if ($needsPriceUpdate) {
                $createPriceAction = app(\App\Actions\ConnectedPrices\CreateConnectedPriceInStripe::class);
                $priceId = $createPriceAction(
                    $variant->stripe_product_id,
                    $variant->stripe_account_id,
                    $variant->price_amount,
                    $variant->currency ?? 'nok',
                    [
                        'nickname' => $variant->variant_name,
                        'metadata' => [
                            'source' => 'variant',
                            'variant_id' => (string) $variant->id,
                            'sku' => $variant->sku ?? '',
                            'barcode' => $variant->barcode ?? '',
                        ],
                    ]
                );

                if ($priceId) {
                    $variant->stripe_price_id = $priceId;
                    $variant->saveQuietly();
                    
                    // Set as default price on the product
                    try {
                        $stripe->products->update(
                            $variant->stripe_product_id,
                            ['default_price' => $priceId],
                            ['stripe_account' => $variant->stripe_account_id]
                        );
                    } catch (Throwable $e) {
                        Log::warning('Failed to set default price on Stripe product', [
                            'variant_id' => $variant->id,
                            'price_id' => $priceId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

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

