<?php

namespace App\Actions\Coupons;

use App\Models\Coupon;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class UpdateCouponInStripe
{
    public function __invoke(Coupon $coupon): bool
    {
        if (!$coupon->stripe_coupon_id || !$coupon->stripe_account_id) {
            Log::warning('Cannot update coupon in Stripe: missing stripe_coupon_id or stripe_account_id', [
                'coupon_id' => $coupon->id,
            ]);
            return false;
        }

        $store = Store::where('stripe_account_id', $coupon->stripe_account_id)->first();
        if (!$store || !$store->hasStripeAccount()) {
            Log::warning('Cannot update coupon in Stripe: store not found or invalid', [
                'coupon_id' => $coupon->id,
                'stripe_account_id' => $coupon->stripe_account_id,
            ]);
            return false;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (!$secret) {
            Log::warning('Cannot update coupon in Stripe: Stripe secret not configured');
            return false;
        }

        $stripe = new StripeClient($secret);

        try {
            // Note: Stripe coupons are immutable, so we can only update metadata
            // For other changes, we'd need to create a new coupon
            // However, we can update the promotion code
            
            if ($coupon->stripe_promotion_code_id) {
                $stripe->promotionCodes->update(
                    $coupon->stripe_promotion_code_id,
                    [
                        'active' => $coupon->active,
                    ],
                    ['stripe_account' => $coupon->stripe_account_id]
                );
            }

            Log::info('Updated coupon in Stripe', [
                'coupon_id' => $coupon->id,
                'stripe_coupon_id' => $coupon->stripe_coupon_id,
                'stripe_account_id' => $coupon->stripe_account_id,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to update coupon in Stripe', [
                'coupon_id' => $coupon->id,
                'stripe_account_id' => $coupon->stripe_account_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            report($e);
            return false;
        }
    }
}

