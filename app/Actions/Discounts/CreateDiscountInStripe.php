<?php

namespace App\Actions\Discounts;

use App\Models\Discount;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class CreateDiscountInStripe
{
    public function __invoke(Discount $discount): ?string
    {
        if (!$discount->stripe_account_id) {
            Log::warning('Cannot create discount in Stripe: missing stripe_account_id', [
                'discount_id' => $discount->id,
            ]);
            return null;
        }

        $store = Store::where('stripe_account_id', $discount->stripe_account_id)->first();
        if (!$store || !$store->hasStripeAccount()) {
            Log::warning('Cannot create discount in Stripe: store not found or invalid', [
                'discount_id' => $discount->id,
                'stripe_account_id' => $discount->stripe_account_id,
            ]);
            return null;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (!$secret) {
            Log::warning('Cannot create discount in Stripe: Stripe secret not configured');
            return null;
        }

        $stripe = new StripeClient($secret);

        try {
            // Create a coupon first (Stripe doesn't have separate "discounts", only coupons/promotion codes)
            $couponData = [
                'duration' => 'once', // Automatic discounts are typically one-time
            ];

            // Set discount type and value
            if ($discount->discount_type === 'percentage') {
                $couponData['percent_off'] = (float) $discount->discount_value;
            } else {
                $couponData['amount_off'] = (int) ($discount->discount_value);
                $couponData['currency'] = strtolower($discount->currency);
            }

            // Set usage limits
            if ($discount->usage_limit) {
                $couponData['max_redemptions'] = $discount->usage_limit;
            }

            // Set minimum amount requirement
            if ($discount->minimum_requirement_type === 'minimum_purchase_amount' && $discount->minimum_requirement_value) {
                $couponData['applies_to'] = [
                    'minimum_amount' => $discount->minimum_requirement_value,
                    'minimum_amount_currency' => strtolower($discount->currency),
                ];
            }

            // Add metadata
            $metadata = [
                'discount_id' => (string) $discount->id,
                'title' => $discount->title,
                'type' => 'automatic_discount',
            ];

            if ($discount->description) {
                $metadata['description'] = $discount->description;
            }

            if ($discount->product_ids) {
                $metadata['product_ids'] = json_encode($discount->product_ids);
            }

            $couponData['metadata'] = $metadata;

            // Create coupon in Stripe
            $stripeCoupon = $stripe->coupons->create(
                $couponData,
                ['stripe_account' => $discount->stripe_account_id]
            );

            // Create promotion code (required for automatic discounts)
            // Use a unique code based on discount ID
            $promotionCode = $stripe->promotionCodes->create([
                'coupon' => $stripeCoupon->id,
                'code' => 'AUTO_' . $discount->id,
                'active' => $discount->active,
            ], ['stripe_account' => $discount->stripe_account_id]);

            Log::info('Created discount in Stripe', [
                'discount_id' => $discount->id,
                'stripe_coupon_id' => $stripeCoupon->id,
                'stripe_promotion_code_id' => $promotionCode->id,
                'stripe_account_id' => $discount->stripe_account_id,
            ]);

            return $promotionCode->id;
        } catch (Throwable $e) {
            Log::error('Failed to create discount in Stripe', [
                'discount_id' => $discount->id,
                'stripe_account_id' => $discount->stripe_account_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            report($e);
            return null;
        }
    }
}

