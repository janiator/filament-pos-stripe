<?php

namespace App\Actions\ConnectedPrices;

use App\Models\ConnectedPaymentLink;
use App\Models\ConnectedPrice;
use App\Models\ConnectedProduct;
use App\Models\ConnectedSubscriptionItem;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Throwable;

class SyncProductPrice
{
    /**
     * Sync product price - create/update/archive/delete prices as needed
     */
    public function __invoke(ConnectedProduct $product): void
    {
        if (! $product->stripe_product_id || ! $product->stripe_account_id) {
            return;
        }

        // Prefer stored price/currency (Filament source of truth); fall back to accessor (e.g. default_price)
        $priceForSync = $product->getRawOriginal('price');
        $currencyForSync = $product->getRawOriginal('currency');
        if ($priceForSync === null || $priceForSync === '') {
            $priceForSync = $product->price;
            $currencyForSync = $product->currency ?? 'nok';
        }
        if ($currencyForSync === null || $currencyForSync === '') {
            $currencyForSync = $product->currency ?? 'nok';
        }
        $newPriceAmount = $priceForSync ? $this->parsePrice($priceForSync, $currencyForSync) : null;
        $currency = strtolower($currencyForSync ?? 'nok');

        if (! $newPriceAmount || $newPriceAmount <= 0) {
            Log::info('No valid price to sync', [
                'product_id' => $product->id,
                'price' => $priceForSync,
            ]);

            return;
        }

        // Get existing active prices for this product
        $existingPrices = ConnectedPrice::where('stripe_product_id', $product->stripe_product_id)
            ->where('stripe_account_id', $product->stripe_account_id)
            ->where('active', true)
            ->where('currency', $currency)
            ->get();

        // Check if we already have a price with this amount
        // First, try to find the price that's already set as default (if it matches)
        // This preserves user's explicit choice when setAsDefault action is used
        $matchingPrice = null;
        if ($product->default_price) {
            $matchingPrice = $existingPrices->first(function ($price) use ($newPriceAmount, $product) {
                return $price->unit_amount === $newPriceAmount
                    && $price->stripe_price_id === $product->default_price;
            });
        }

        // If no match with current default_price, find any matching price
        if (! $matchingPrice) {
            $matchingPrice = $existingPrices->first(function ($price) use ($newPriceAmount) {
                return $price->unit_amount === $newPriceAmount;
            });
        }

        if ($matchingPrice) {
            // Price already exists with this amount
            // Only update default_price if it's different (preserves user's explicit choice)
            if ($product->default_price !== $matchingPrice->stripe_price_id) {
                $product->default_price = $matchingPrice->stripe_price_id;
                $product->saveQuietly();
            }
            Log::info('Price already exists, using existing price', [
                'product_id' => $product->id,
                'price_id' => $matchingPrice->stripe_price_id,
                'amount' => $newPriceAmount,
                'was_already_default' => $product->default_price === $matchingPrice->stripe_price_id,
            ]);

            return;
        }

        // Create new price
        $createPriceAction = new CreateConnectedPriceInStripe;
        $newPriceId = $createPriceAction(
            $product->stripe_product_id,
            $product->stripe_account_id,
            $newPriceAmount,
            $currency,
            [
                'metadata' => [
                    'source' => 'product_form',
                    'product_id' => $product->id,
                ],
            ]
        );

        if (! $newPriceId) {
            Log::error('Failed to create new price', [
                'product_id' => $product->id,
                'amount' => $newPriceAmount,
            ]);

            return;
        }

        // Set as default price
        $product->default_price = $newPriceId;
        $product->saveQuietly();

        // Handle old prices - archive if used, delete if not
        foreach ($existingPrices as $oldPrice) {
            if ($this->priceHasBeenUsed($oldPrice)) {
                // Archive the price (set active=false)
                $this->archivePrice($oldPrice);
                Log::info('Archived used price', [
                    'product_id' => $product->id,
                    'price_id' => $oldPrice->stripe_price_id,
                    'amount' => $oldPrice->unit_amount,
                ]);
            } else {
                // Delete the price
                $this->deletePrice($oldPrice);
                Log::info('Deleted unused price', [
                    'product_id' => $product->id,
                    'price_id' => $oldPrice->stripe_price_id,
                    'amount' => $oldPrice->unit_amount,
                ]);
            }
        }

        Log::info('Synced product price', [
            'product_id' => $product->id,
            'new_price_id' => $newPriceId,
            'amount' => $newPriceAmount,
            'currency' => $currency,
        ]);
    }

    /**
     * Check if a price has been used in any purchases
     */
    protected function priceHasBeenUsed(ConnectedPrice $price): bool
    {
        // Check payment links
        $usedInPaymentLinks = ConnectedPaymentLink::where('stripe_price_id', $price->stripe_price_id)
            ->exists();

        if ($usedInPaymentLinks) {
            return true;
        }

        // Check subscription items
        $usedInSubscriptions = ConnectedSubscriptionItem::where('connected_price', $price->stripe_price_id)
            ->exists();

        if ($usedInSubscriptions) {
            return true;
        }

        // Check Stripe API for usage in payment intents, charges, etc.
        // This is a more thorough check but requires API calls
        try {
            $secret = config('cashier.secret') ?? config('services.stripe.secret');
            if (! $secret) {
                return false;
            }

            $stripe = new StripeClient($secret);

            // Check payment intents - search for this price ID
            // Note: Payment intents don't directly store price_id, but we can check metadata
            $paymentIntents = $stripe->paymentIntents->search([
                'query' => "metadata['price_id']:'{$price->stripe_price_id}'",
                'limit' => 10,
            ], [
                'stripe_account' => $price->stripe_account_id,
            ]);

            if ($paymentIntents && count($paymentIntents->data) > 0) {
                return true;
            }

            // Also check if price appears in any payment link line items
            // Payment links store price IDs in their line_items
            $paymentLinks = $stripe->paymentLinks->all(
                ['limit' => 100],
                ['stripe_account' => $price->stripe_account_id]
            );

            foreach ($paymentLinks->autoPagingIterator() as $link) {
                if (isset($link->line_items) && isset($link->line_items->data)) {
                    foreach ($link->line_items->data as $lineItem) {
                        $lineItemPriceId = is_string($lineItem->price) ? $lineItem->price : ($lineItem->price->id ?? null);
                        if ($lineItemPriceId === $price->stripe_price_id) {
                            return true;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('Failed to check price usage in Stripe API', [
                'price_id' => $price->stripe_price_id,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Archive a price in Stripe (set active=false)
     */
    protected function archivePrice(ConnectedPrice $price): void
    {
        try {
            $secret = config('cashier.secret') ?? config('services.stripe.secret');
            if (! $secret) {
                return;
            }

            $stripe = new StripeClient($secret);

            $stripe->prices->update(
                $price->stripe_price_id,
                ['active' => false],
                ['stripe_account' => $price->stripe_account_id]
            );

            // Update local record
            $price->active = false;
            $price->save();
        } catch (Throwable $e) {
            Log::error('Failed to archive price', [
                'price_id' => $price->stripe_price_id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    /**
     * Delete a price from Stripe
     */
    protected function deletePrice(ConnectedPrice $price): void
    {
        try {
            $secret = config('cashier.secret') ?? config('services.stripe.secret');
            if (! $secret) {
                return;
            }

            $stripe = new StripeClient($secret);

            // Try to delete, but Stripe may not allow deletion if price has been used
            // In that case, we'll archive it instead
            try {
                $stripe->prices->delete(
                    $price->stripe_price_id,
                    [],
                    ['stripe_account' => $price->stripe_account_id]
                );

                // Delete local record
                $price->delete();
            } catch (Throwable $deleteError) {
                // If deletion fails, archive instead
                Log::warning('Price deletion failed, archiving instead', [
                    'price_id' => $price->stripe_price_id,
                    'error' => $deleteError->getMessage(),
                ]);
                $this->archivePrice($price);
            }
        } catch (Throwable $e) {
            Log::error('Failed to delete price', [
                'price_id' => $price->stripe_price_id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    /**
     * Parse price string to cents
     */
    protected function parsePrice($price, string $currency = 'nok'): int
    {
        // If already numeric, convert directly
        if (is_numeric($price)) {
            return (int) round((float) $price * 100);
        }

        // Convert to string and remove currency symbols and whitespace
        $price = (string) $price;
        $price = preg_replace('/[^\d.,]/', '', $price);

        if (empty($price)) {
            return 0;
        }

        // Handle Norwegian format (1.234,56) or US format (1,234.56)
        if (strpos($price, ',') !== false && strpos($price, '.') !== false) {
            // Determine which is decimal separator
            $lastComma = strrpos($price, ',');
            $lastDot = strrpos($price, '.');

            if ($lastComma > $lastDot) {
                // Norwegian format: 1.234,56
                $price = str_replace('.', '', $price);
                $price = str_replace(',', '.', $price);
            } else {
                // US format: 1,234.56
                $price = str_replace(',', '', $price);
            }
        } else {
            // Only one separator, assume it's decimal
            $price = str_replace(',', '.', $price);
        }

        // Convert to cents/øre
        return (int) round((float) $price * 100);
    }
}
