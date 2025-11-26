<?php

namespace App\Actions\ConnectedPrices;

use App\Models\ConnectedPrice;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class CreateConnectedPriceInStripe
{
    /**
     * Create a price in Stripe for a product
     * 
     * @param string $stripeProductId The Stripe product ID
     * @param string $stripeAccountId The Stripe account ID
     * @param int $unitAmount Amount in smallest currency unit (e.g., cents)
     * @param string $currency Currency code (e.g., 'nok', 'usd')
     * @param array $options Additional options (nickname, metadata, etc.)
     * @return string|null The Stripe price ID or null on failure
     */
    public function __invoke(
        string $stripeProductId,
        string $stripeAccountId,
        int $unitAmount,
        string $currency = 'nok',
        array $options = []
    ): ?string {
        $store = Store::where('stripe_account_id', $stripeAccountId)->first();
        if (! $store || ! $store->hasStripeAccount()) {
            Log::warning('Cannot create price in Stripe: store not found or invalid', [
                'stripe_account_id' => $stripeAccountId,
            ]);
            return null;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (! $secret) {
            Log::warning('Cannot create price in Stripe: Stripe secret not configured');
            return null;
        }

        $stripe = new StripeClient($secret);

        try {
            $createData = [
                'product' => $stripeProductId,
                'unit_amount' => $unitAmount,
                'currency' => strtolower($currency),
            ];

            // Add optional fields
            if (isset($options['nickname'])) {
                $createData['nickname'] = $options['nickname'];
            }

            if (isset($options['metadata']) && is_array($options['metadata'])) {
                // Ensure all metadata values are strings
                $metadata = [];
                foreach ($options['metadata'] as $key => $value) {
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
                
                if (!empty($metadata)) {
                    $createData['metadata'] = $metadata;
                }
            }

            // Create price in Stripe
            $stripePrice = $stripe->prices->create(
                $createData,
                ['stripe_account' => $stripeAccountId]
            );

            // Save price locally
            ConnectedPrice::updateOrCreate(
                [
                    'stripe_price_id' => $stripePrice->id,
                    'stripe_account_id' => $stripeAccountId,
                ],
                [
                    'stripe_product_id' => $stripeProductId,
                    'unit_amount' => $stripePrice->unit_amount,
                    'currency' => $stripePrice->currency,
                    'type' => $stripePrice->type,
                    'active' => $stripePrice->active,
                    'billing_scheme' => $stripePrice->billing_scheme ?? null,
                    'recurring_interval' => $stripePrice->recurring->interval ?? null,
                    'recurring_interval_count' => $stripePrice->recurring->interval_count ?? null,
                    'recurring_usage_type' => $stripePrice->recurring->usage_type ?? null,
                    'metadata' => $stripePrice->metadata ? (array) $stripePrice->metadata : null,
                    'nickname' => $stripePrice->nickname ?? null,
                ]
            );

            Log::info('Created price in Stripe', [
                'stripe_price_id' => $stripePrice->id,
                'stripe_product_id' => $stripeProductId,
                'stripe_account_id' => $stripeAccountId,
                'unit_amount' => $unitAmount,
                'currency' => $currency,
            ]);

            return $stripePrice->id;
        } catch (Throwable $e) {
            Log::error('Failed to create price in Stripe', [
                'stripe_product_id' => $stripeProductId,
                'stripe_account_id' => $stripeAccountId,
                'unit_amount' => $unitAmount,
                'currency' => $currency,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            report($e);
            return null;
        }
    }
}

