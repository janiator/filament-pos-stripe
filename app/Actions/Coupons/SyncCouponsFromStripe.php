<?php

namespace App\Actions\Coupons;

use App\Models\Coupon;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class SyncCouponsFromStripe
{
    public function __invoke(Store $store): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        if (!$store->hasStripeAccount()) {
            $result['errors'][] = "Store {$store->id} does not have a Stripe account";
            return $result;
        }

        $stripeAccountId = $store->stripe_account_id;
        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        
        if (!$secret) {
            $result['errors'][] = 'Stripe secret not configured';
            return $result;
        }

        $stripe = new StripeClient($secret);

        try {
            // Get all coupons from Stripe
            $coupons = $stripe->coupons->all(
                ['limit' => 100],
                ['stripe_account' => $stripeAccountId]
            );

            foreach ($coupons->autoPagingIterator() as $stripeCoupon) {
                try {
                    $couponData = [
                        'stripe_account_id' => $stripeAccountId,
                        'stripe_coupon_id' => $stripeCoupon->id,
                        'discount_type' => $stripeCoupon->percent_off ? 'percentage' : 'fixed_amount',
                        'discount_value' => $stripeCoupon->percent_off ?? ($stripeCoupon->amount_off ?? 0),
                        'currency' => strtolower($stripeCoupon->currency ?? 'nok'),
                        'duration' => $stripeCoupon->duration,
                        'duration_in_months' => $stripeCoupon->duration_in_months ?? null,
                        'max_redemptions' => $stripeCoupon->max_redemptions ?? null,
                        'times_redeemed' => $stripeCoupon->times_redeemed ?? 0,
                        'redeem_by' => $stripeCoupon->redeem_by ? \Carbon\Carbon::createFromTimestamp($stripeCoupon->redeem_by) : null,
                        'minimum_amount' => $stripeCoupon->applies_to->minimum_amount ?? null,
                        'minimum_amount_currency' => $stripeCoupon->applies_to->minimum_amount_currency ?? null,
                        'metadata' => $stripeCoupon->metadata ? (array) $stripeCoupon->metadata : null,
                        'active' => true, // Coupons from Stripe are active by default
                    ];

                    // Try to find promotion code for this coupon
                    $promotionCodes = $stripe->promotionCodes->all(
                        ['coupon' => $stripeCoupon->id, 'limit' => 1],
                        ['stripe_account' => $stripeAccountId]
                    );

                    if ($promotionCodes->data && count($promotionCodes->data) > 0) {
                        $promotionCode = $promotionCodes->data[0];
                        $couponData['stripe_promotion_code_id'] = $promotionCode->id;
                        $couponData['code'] = $promotionCode->code;
                        $couponData['active'] = $promotionCode->active;
                    }

                    $coupon = Coupon::updateOrCreate(
                        [
                            'stripe_coupon_id' => $stripeCoupon->id,
                            'stripe_account_id' => $stripeAccountId,
                        ],
                        array_merge($couponData, [
                            'store_id' => $store->id,
                        ])
                    );

                    if ($coupon->wasRecentlyCreated) {
                        $result['created']++;
                    } else {
                        $result['updated']++;
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Coupon {$stripeCoupon->id}: " . $e->getMessage();
                    report($e);
                }
            }
        } catch (Throwable $e) {
            $result['errors'][] = "Failed to sync coupons: " . $e->getMessage();
            report($e);
        }

        return $result;
    }
}

