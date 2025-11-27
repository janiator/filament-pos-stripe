<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectedPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_price_id',
        'stripe_account_id',
        'stripe_product_id',
        'unit_amount',
        'currency',
        'type',
        'recurring_interval',
        'recurring_interval_count',
        'recurring_usage_type',
        'recurring_aggregate_usage',
        'active',
        'metadata',
        'nickname',
        'billing_scheme',
        'tiers_mode',
    ];

    protected $casts = [
        'unit_amount' => 'integer',
        'recurring_interval_count' => 'integer',
        'active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the store that owns this price via stripe_account_id
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the product for this price
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ConnectedProduct::class, 'stripe_product_id', 'stripe_product_id')
            ->where('stripe_account_id', $this->stripe_account_id);
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->unit_amount / 100, 2) . ' ' . strtoupper($this->currency ?? 'USD');
    }

    /**
     * Get recurring description
     */
    public function getRecurringDescriptionAttribute(): ?string
    {
        if ($this->type !== 'recurring' || !$this->recurring_interval) {
            return null;
        }

        $interval = ucfirst($this->recurring_interval);
        $count = $this->recurring_interval_count > 1 ? "every {$this->recurring_interval_count} " : '';
        
        return $count . $interval;
    }

    /**
     * Get the variant associated with this price
     */
    public function variant(): ?\Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductVariant::class, 'stripe_price_id', 'stripe_price_id')
            ->where('stripe_account_id', $this->stripe_account_id);
    }

}
