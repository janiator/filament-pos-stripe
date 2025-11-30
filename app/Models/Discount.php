<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'stripe_account_id',
        'stripe_promotion_code_id',
        'title',
        'description',
        'discount_type',
        'discount_value',
        'currency',
        'active',
        'starts_at',
        'ends_at',
        'customer_selection',
        'customer_ids',
        'minimum_requirement_type',
        'minimum_requirement_value',
        'applicable_to',
        'product_ids',
        'collection_ids',
        'usage_limit',
        'usage_limit_per_customer',
        'usage_count',
        'priority',
        'metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'discount_value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'customer_ids' => 'array',
        'product_ids' => 'array',
        'collection_ids' => 'array',
        'usage_limit' => 'integer',
        'usage_limit_per_customer' => 'integer',
        'usage_count' => 'integer',
        'priority' => 'integer',
        'minimum_requirement_value' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the store that owns this discount
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Check if discount is currently valid
     */
    public function isValid(): bool
    {
        if (!$this->active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Check if discount applies to a specific product
     */
    public function appliesToProduct($productId): bool
    {
        if ($this->applicable_to === 'all_products') {
            return true;
        }

        if ($this->applicable_to === 'specific_products') {
            return in_array($productId, $this->product_ids ?? []);
        }

        return false;
    }

    /**
     * Check if discount applies to a specific customer
     */
    public function appliesToCustomer($customerId): bool
    {
        if ($this->customer_selection === 'all') {
            return true;
        }

        if ($this->customer_selection === 'specific_customers') {
            return in_array($customerId, $this->customer_ids ?? []);
        }

        return false;
    }

    /**
     * Check if minimum requirement is met
     */
    public function meetsMinimumRequirement($amount, $quantity = 1): bool
    {
        if ($this->minimum_requirement_type === 'none') {
            return true;
        }

        if ($this->minimum_requirement_type === 'minimum_purchase_amount') {
            return $amount >= ($this->minimum_requirement_value ?? 0);
        }

        if ($this->minimum_requirement_type === 'minimum_quantity') {
            return $quantity >= ($this->minimum_requirement_value ?? 0);
        }

        return false;
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
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
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
     * Boot the model and set up event listeners
     */
    protected static function booted(): void
    {
        // Create in Stripe when discount is created
        static::created(function (Discount $discount) {
            if ($discount->stripe_account_id) {
                $createAction = app(\App\Actions\Discounts\CreateDiscountInStripe::class);
                $promotionCodeId = $createAction($discount);
                
                if ($promotionCodeId) {
                    $discount->stripe_promotion_code_id = $promotionCodeId;
                    $discount->saveQuietly();
                }
            }
        });

        // Update in Stripe when discount is updated
        static::updated(function (Discount $discount) {
            if ($discount->stripe_promotion_code_id && $discount->stripe_account_id) {
                // Only sync if relevant fields changed
                if ($discount->wasChanged('active')) {
                    $updateAction = app(\App\Actions\Discounts\UpdateDiscountInStripe::class);
                    $updateAction($discount);
                }
            }
        });
    }
}
