<?php

namespace App\Actions\ConnectedProducts;

use App\Models\ConnectedPrice;
use App\Models\ConnectedProduct;
use App\Models\Store;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;

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
            if (! $store->hasStripeAccount() || ! $store->stripe_account_id) {
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
            $products = $stripe->products->all(
                ['limit' => 100],
                ['stripe_account' => $store->stripe_account_id]
            );

            foreach ($products->autoPagingIterator() as $product) {
                $result['total']++;

                try {
                    // Ensure stripe_account_id is set
                    if (! $store->stripe_account_id) {
                        $result['errors'][] = "Product {$product->id}: Store does not have a stripe_account_id";
                        continue;
                    }

                    $data = [
                        'stripe_product_id' => $product->id,
                        'stripe_account_id' => $store->stripe_account_id,
                        'name' => $product->name ?? $product->id, // Use product ID as fallback if name is null
                        'description' => $product->description,
                        'active' => $product->active,
                        'images' => $product->images ? (array) $product->images : null,
                        'metadata' => $product->metadata ? (array) $product->metadata : null,
                        'type' => $product->type ?? 'service',
                        'url' => $product->url ?? null,
                    ];

                    $productRecord = ConnectedProduct::where('stripe_product_id', $product->id)
                        ->where('stripe_account_id', $store->stripe_account_id)
                        ->first();

                    if ($productRecord) {
                        $productRecord->fill($data);
                        $productRecord->save();
                        $result['updated']++;
                    } else {
                        ConnectedProduct::create($data);
                        $result['created']++;
                    }

                    // Also sync prices for this product
                    if (isset($product->default_price)) {
                        try {
                            $price = $stripe->prices->retrieve(
                                $product->default_price,
                                [],
                                ['stripe_account' => $store->stripe_account_id]
                            );
                            $this->syncPrice($price, $store);
                        } catch (Throwable $e) {
                            // Log but don't fail the product sync
                            report($e);
                        }
                    }

                    // Get all prices for this product
                    $prices = $stripe->prices->all(
                        ['product' => $product->id, 'limit' => 100],
                        ['stripe_account' => $store->stripe_account_id]
                    );

                    foreach ($prices->autoPagingIterator() as $price) {
                        try {
                            $this->syncPrice($price, $store);
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

    protected function syncPrice($price, Store $store): void
    {
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

