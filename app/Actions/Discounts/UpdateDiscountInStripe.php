<?php

namespace App\Actions\Discounts;

use App\Models\Discount;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class UpdateDiscountInStripe
{
    public function __invoke(Discount $discount): bool
    {
        if (!$discount->stripe_promotion_code_id || !$discount->stripe_account_id) {
            Log::warning('Cannot update discount in Stripe: missing stripe_promotion_code_id or stripe_account_id', [
                'discount_id' => $discount->id,
            ]);
            return false;
        }

        $store = Store::where('stripe_account_id', $discount->stripe_account_id)->first();
        if (!$store || !$store->hasStripeAccount()) {
            Log::warning('Cannot update discount in Stripe: store not found or invalid', [
                'discount_id' => $discount->id,
                'stripe_account_id' => $discount->stripe_account_id,
            ]);
            return false;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (!$secret) {
            Log::warning('Cannot update discount in Stripe: Stripe secret not configured');
            return false;
        }

        $stripe = new StripeClient($secret);

        try {
            // Update promotion code active status
            // Note: Stripe coupons are immutable, so we can only update the promotion code
            $stripe->promotionCodes->update(
                $discount->stripe_promotion_code_id,
                [
                    'active' => $discount->active,
                ],
                ['stripe_account' => $discount->stripe_account_id]
            );

            Log::info('Updated discount in Stripe', [
                'discount_id' => $discount->id,
                'stripe_promotion_code_id' => $discount->stripe_promotion_code_id,
                'stripe_account_id' => $discount->stripe_account_id,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to update discount in Stripe', [
                'discount_id' => $discount->id,
                'stripe_account_id' => $discount->stripe_account_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            report($e);
            return false;
        }
    }
}

