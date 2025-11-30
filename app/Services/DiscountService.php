<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Models\ConnectedProduct;
use Illuminate\Support\Collection;

class DiscountService
{
    /**
     * Get applicable automatic discounts for a product/variant
     */
    public function getApplicableDiscounts(
        ProductVariant|ConnectedProduct $product,
        ?string $customerId = null,
        int $quantity = 1,
        int $cartTotal = 0
    ): Collection {
        $storeId = $product instanceof ProductVariant 
            ? $product->product->store_id 
            : $product->store_id;

        $stripeAccountId = $product instanceof ProductVariant
            ? $product->stripe_account_id
            : $product->stripe_account_id;

        $productId = $product instanceof ProductVariant
            ? $product->product->id
            : $product->id;

        return Discount::where('stripe_account_id', $stripeAccountId)
            ->where('active', true)
            ->where(function ($query) use ($productId) {
                $query->where('applicable_to', 'all_products')
                    ->orWhereJsonContains('product_ids', $productId);
            })
            ->where(function ($query) use ($customerId) {
                if ($customerId) {
                    $query->where('customer_selection', 'all')
                        ->orWhereJsonContains('customer_ids', $customerId);
                } else {
                    $query->where('customer_selection', 'all');
                }
            })
            ->where(function ($query) use ($cartTotal, $quantity) {
                $query->where('minimum_requirement_type', 'none')
                    ->orWhere(function ($q) use ($cartTotal, $quantity) {
                        $q->where('minimum_requirement_type', 'minimum_purchase_amount')
                            ->where('minimum_requirement_value', '<=', $cartTotal);
                    })
                    ->orWhere(function ($q) use ($quantity) {
                        $q->where('minimum_requirement_type', 'minimum_quantity')
                            ->where('minimum_requirement_value', '<=', $quantity);
                    });
            })
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Calculate the best discount for a product/variant
     * Returns the discount with highest savings
     */
    public function getBestDiscount(
        ProductVariant|ConnectedProduct $product,
        ?string $customerId = null,
        int $quantity = 1,
        int $cartTotal = 0
    ): ?Discount {
        $discounts = $this->getApplicableDiscounts($product, $customerId, $quantity, $cartTotal);

        if ($discounts->isEmpty()) {
            return null;
        }

        $priceAmount = $product instanceof ProductVariant
            ? $product->price_amount
            : ($product->prices()->first()?->unit_amount ?? 0);

        $bestDiscount = null;
        $bestSavings = 0;

        foreach ($discounts as $discount) {
            $savings = $discount->calculateDiscount($priceAmount);
            if ($savings > $bestSavings) {
                $bestSavings = $savings;
                $bestDiscount = $discount;
            }
        }

        return $bestDiscount;
    }

    /**
     * Calculate discounted price for a product/variant
     */
    public function calculateDiscountedPrice(
        ProductVariant|ConnectedProduct $product,
        ?Discount $discount = null,
        ?string $customerId = null,
        int $quantity = 1,
        int $cartTotal = 0
    ): array {
        $priceAmount = $product instanceof ProductVariant
            ? $product->price_amount
            : ($product->prices()->first()?->unit_amount ?? 0);

        if (!$discount) {
            $discount = $this->getBestDiscount($product, $customerId, $quantity, $cartTotal);
        }

        if (!$discount) {
            return [
                'original_price' => $priceAmount,
                'discounted_price' => $priceAmount,
                'discount_amount' => 0,
                'discount' => null,
            ];
        }

        $discountAmount = $discount->calculateDiscount($priceAmount);
        $discountedPrice = max(0, $priceAmount - $discountAmount);

        return [
            'original_price' => $priceAmount,
            'discounted_price' => $discountedPrice,
            'discount_amount' => $discountAmount,
            'discount' => $discount,
        ];
    }

    /**
     * Validate and apply a coupon code
     */
    public function validateCoupon(string $code, string $stripeAccountId, int $amount = 0, string $currency = 'nok'): ?Coupon
    {
        $coupon = Coupon::findByCode($code, $stripeAccountId);

        if (!$coupon) {
            return null;
        }

        if (!$coupon->isValid()) {
            return null;
        }

        if (!$coupon->meetsMinimumAmount($amount, $currency)) {
            return null;
        }

        return $coupon;
    }

    /**
     * Calculate discount with coupon
     */
    public function calculateWithCoupon(int $priceAmount, Coupon $coupon): array
    {
        $discountAmount = $coupon->calculateDiscount($priceAmount);
        $discountedPrice = max(0, $priceAmount - $discountAmount);

        return [
            'original_price' => $priceAmount,
            'discounted_price' => $discountedPrice,
            'discount_amount' => $discountAmount,
            'coupon' => $coupon,
        ];
    }
}

