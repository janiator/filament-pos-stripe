<?php

namespace App\Jobs;

use App\Actions\ConnectedPrices\CreateConnectedPriceInStripe;
use App\Actions\ConnectedPrices\SyncProductPrice;
use App\Actions\ConnectedProducts\CreateConnectedProductInStripe;
use App\Actions\ConnectedProducts\CreateVariantProductInStripe;
use App\Models\ConnectedProduct;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

class EnsureProductStripeIdJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $connectedProductId
    ) {}

    public function handle(): void
    {
        $product = ConnectedProduct::with(['store', 'variants'])->find($this->connectedProductId);
        if (! $product || ! $product->stripe_account_id) {
            return;
        }

        $store = $product->store;
        if (! $store || ! $store->hasStripeAccount()) {
            return;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (! $secret) {
            return;
        }

        $stripe = new StripeClient($secret);
        $accountId = $product->stripe_account_id;

        $productNeedsCreate = false;
        if ($product->stripe_product_id) {
            try {
                $stripe->products->retrieve(
                    $product->stripe_product_id,
                    [],
                    ['stripe_account' => $accountId]
                );
            } catch (InvalidRequestException $e) {
                if ($e->getHttpStatus() === 404 || str_contains(strtolower($e->getMessage()), 'no such product')) {
                    $productNeedsCreate = true;
                    $product->stripe_product_id = null;
                    $product->default_price = null;
                    $product->saveQuietly();
                    Log::info('EnsureProductStripeIdJob: product missing in Stripe, will recreate', [
                        'product_id' => $product->id,
                        'name' => $product->name,
                    ]);
                } else {
                    throw $e;
                }
            }
        } else {
            $productNeedsCreate = true;
        }

        if ($productNeedsCreate) {
            $createProduct = new CreateConnectedProductInStripe;
            $stripeProductId = $createProduct($product);
            if ($stripeProductId) {
                $product->stripe_product_id = $stripeProductId;
                $product->saveQuietly();

                if (! $product->isVariable() && ! $product->no_price_in_pos && $product->price) {
                    try {
                        $syncPrice = new SyncProductPrice;
                        $syncPrice($product);
                    } catch (\Throwable $e) {
                        Log::warning('EnsureProductStripeIdJob: failed to sync product price', [
                            'product_id' => $product->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        $createVariant = new CreateVariantProductInStripe;
        $createPrice = new CreateConnectedPriceInStripe;
        foreach ($product->variants as $variant) {
            $variantNeedsCreate = false;
            if ($variant->stripe_product_id) {
                try {
                    $stripe->products->retrieve(
                        $variant->stripe_product_id,
                        [],
                        ['stripe_account' => $accountId]
                    );
                } catch (InvalidRequestException $e) {
                    if ($e->getHttpStatus() === 404 || str_contains(strtolower($e->getMessage()), 'no such product')) {
                        $variantNeedsCreate = true;
                        $variant->stripe_product_id = null;
                        $variant->stripe_price_id = null;
                        $variant->saveQuietly();
                    } else {
                        throw $e;
                    }
                }
            } else {
                $variantNeedsCreate = true;
            }

            if ($variantNeedsCreate) {
                $stripeVariantId = $createVariant($variant);
                if ($stripeVariantId) {
                    $variant->stripe_product_id = $stripeVariantId;
                    $variant->saveQuietly();
                    if (! $variant->no_price_in_pos && $variant->price_amount && $variant->price_amount > 0) {
                        $priceId = $createPrice(
                            $stripeVariantId,
                            $variant->stripe_account_id,
                            $variant->price_amount,
                            $variant->currency ?? 'nok',
                            [
                                'nickname' => $variant->variant_name ?? $variant->full_title,
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
                        }
                    }
                }
            }
        }
    }
}
