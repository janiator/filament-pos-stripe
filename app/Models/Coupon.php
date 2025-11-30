<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'stripe_account_id',
        'stripe_coupon_id',
        'stripe_promotion_code_id',
        'code',
        'discount_type',
        'discount_value',
        'currency',
        'duration',
        'duration_in_months',
        'active',
        'redeem_by',
        'max_redemptions',
        'times_redeemed',
        'minimum_amount',
        'minimum_amount_currency',
        'metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'discount_value' => 'decimal:2',
        'duration_in_months' => 'integer',
        'redeem_by' => 'datetime',
        'max_redemptions' => 'integer',
        'times_redeemed' => 'integer',
        'minimum_amount' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the store that owns this coupon
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Check if coupon is currently valid
     */
    public function isValid(): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->redeem_by && Carbon::now()->gt($this->redeem_by)) {
            return false;
        }

        if ($this->max_redemptions && $this->times_redeemed >= $this->max_redemptions) {
            return false;
        }

        return true;
    }

    /**
     * Check if minimum amount requirement is met
     */
    public function meetsMinimumAmount($amount, $currency = 'nok'): bool
    {
        if (!$this->minimum_amount) {
            return true;
        }

        // For now, simple currency check (could be enhanced with currency conversion)
        if ($this->minimum_amount_currency && $this->minimum_amount_currency !== $currency) {
            return false;
        }

        return $amount >= $this->minimum_amount;
    }

    /**
     * Calculate discount amount for a given price
     */
    public function calculateDiscount($priceAmount): int
    {
        if ($this->discount_type === 'percentage') {
            return (int) round($priceAmount * ($this->discount_value / 100));
        }

        // Fixed amount
        return (int) min($this->discount_value, $priceAmount);
    }

    /**
     * Increment redemption count
     */
    public function incrementRedemptions(): void
    {
        $this->increment('times_redeemed');
    }

    /**
     * Get formatted discount value
     */
    public function getFormattedValueAttribute(): string
    {
        if ($this->discount_type === 'percentage') {
            return number_format($this->discount_value, 0) . '%';
        }

        return number_format($this->discount_value / 100, 2) . ' ' . strtoupper($this->currency);
    }

    /**
     * Find coupon by code (case-insensitive)
     */
    public static function findByCode(string $code, string $stripeAccountId): ?self
    {
        return static::where('stripe_account_id', $stripeAccountId)
            ->whereRaw('LOWER(code) = ?', [strtolower($code)])
            ->first();
    }

    /**
     * Boot the model and set up event listeners
     */
    protected static function booted(): void
    {
        // Create in Stripe when coupon is created
        static::created(function (Coupon $coupon) {
            if ($coupon->stripe_account_id && !$coupon->stripe_coupon_id) {
                $createAction = app(\App\Actions\Coupons\CreateCouponInStripe::class);
                $couponId = $createAction($coupon);
                
                if ($couponId) {
                    $coupon->stripe_coupon_id = $couponId;
                    $coupon->saveQuietly();
                }
            }
        });

        // Update in Stripe when coupon is updated
        static::updated(function (Coupon $coupon) {
            if ($coupon->stripe_coupon_id && $coupon->stripe_account_id) {
                // Only sync if relevant fields changed
                if ($coupon->wasChanged('active')) {
                    $updateAction = app(\App\Actions\Coupons\UpdateCouponInStripe::class);
                    $updateAction($coupon);
                }
            }
        });
    }
}
