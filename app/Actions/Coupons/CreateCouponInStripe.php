<?php

namespace App\Actions\Coupons;

use App\Models\Coupon;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class CreateCouponInStripe
{
    public function __invoke(Coupon $coupon): ?string
    {
        if (!$coupon->stripe_account_id) {
            Log::warning('Cannot create coupon in Stripe: missing stripe_account_id', [
                'coupon_id' => $coupon->id,
            ]);
            return null;
        }

        $store = Store::where('stripe_account_id', $coupon->stripe_account_id)->first();
        if (!$store || !$store->hasStripeAccount()) {
            Log::warning('Cannot create coupon in Stripe: store not found or invalid', [
                'coupon_id' => $coupon->id,
                'stripe_account_id' => $coupon->stripe_account_id,
            ]);
            return null;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (!$secret) {
            Log::warning('Cannot create coupon in Stripe: Stripe secret not configured');
            return null;
        }

        $stripe = new StripeClient($secret);

        try {
            // Prepare coupon data
            $couponData = [
                'duration' => $coupon->duration,
            ];

            // Set discount type and value
            if ($coupon->discount_type === 'percentage') {
                $couponData['percent_off'] = (float) $coupon->discount_value;
            } else {
                $couponData['amount_off'] = (int) ($coupon->discount_value);
                $couponData['currency'] = strtolower($coupon->currency);
            }

            // Set duration for repeating coupons
            if ($coupon->duration === 'repeating' && $coupon->duration_in_months) {
                $couponData['duration_in_months'] = $coupon->duration_in_months;
            }

            // Set usage limits
            if ($coupon->max_redemptions) {
                $couponData['max_redemptions'] = $coupon->max_redemptions;
            }

            // Set redeem by date
            if ($coupon->redeem_by) {
                $couponData['redeem_by'] = $coupon->redeem_by->timestamp;
            }

            // Set minimum amount
            if ($coupon->minimum_amount) {
                $couponData['applies_to'] = [
                    'minimum_amount' => $coupon->minimum_amount,
                    'minimum_amount_currency' => strtolower($coupon->minimum_amount_currency ?? $coupon->currency),
                ];
            }

            // Add metadata
            if ($coupon->metadata) {
                $metadata = [];
                foreach ($coupon->metadata as $key => $value) {
                    if (is_string($key) && is_string($value)) {
                        $metadata[$key] = $value;
                    } elseif (is_scalar($value)) {
                        $metadata[$key] = (string) $value;
                    }
                }
                if (!empty($metadata)) {
                    $couponData['metadata'] = $metadata;
                }
            }

            // Create coupon in Stripe
            $stripeCoupon = $stripe->coupons->create(
                $couponData,
                ['stripe_account' => $coupon->stripe_account_id]
            );

            // Create promotion code if code is set
            $promotionCodeId = null;
            if ($coupon->code) {
                $promotionCode = $stripe->promotionCodes->create([
                    'coupon' => $stripeCoupon->id,
                    'code' => $coupon->code,
                    'active' => $coupon->active,
                ], ['stripe_account' => $coupon->stripe_account_id]);
                
                $promotionCodeId = $promotionCode->id;
            }

            Log::info('Created coupon in Stripe', [
                'coupon_id' => $coupon->id,
                'stripe_coupon_id' => $stripeCoupon->id,
                'stripe_promotion_code_id' => $promotionCodeId,
                'stripe_account_id' => $coupon->stripe_account_id,
                'code' => $coupon->code,
            ]);

            return $stripeCoupon->id;
        } catch (Throwable $e) {
            Log::error('Failed to create coupon in Stripe', [
                'coupon_id' => $coupon->id,
                'stripe_account_id' => $coupon->stripe_account_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            report($e);
            return null;
        }
    }
}

