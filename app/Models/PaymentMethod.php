<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'code',
        'provider',
        'provider_method',
        'enabled',
        'pos_suitable',
        'sort_order',
        'config',
        'saf_t_payment_code',
        'saf_t_event_code',
        'description',
        'background_color',
        'icon_color',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'pos_suitable' => 'boolean',
        'sort_order' => 'integer',
        'config' => 'array',
    ];

    /**
     * Get the store that owns this payment method
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Scope to get only enabled payment methods
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope to get only POS-suitable payment methods
     */
    public function scopePosSuitable($query)
    {
        return $query->where('pos_suitable', true);
    }

    /**
     * Check if this is a cash payment method
     */
    public function isCash(): bool
    {
        return $this->code === 'cash' || $this->provider === 'cash';
    }

    /**
     * Check if this is a Stripe payment method
     */
    public function isStripe(): bool
    {
        return $this->provider === 'stripe';
    }
}
