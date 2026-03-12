<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'minimum_amount_kroner',
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
        'minimum_amount_kroner' => 'integer',
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
     * POS devices this payment method is restricted to. Empty = available on all devices.
     */
    public function posDevices(): BelongsToMany
    {
        return $this->belongsToMany(PosDevice::class, 'payment_method_pos_device');
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
     * Scope to payment methods available on the given POS device.
     * When null, no filter. When set: no devices restricted OR this device in list.
     */
    public function scopeAvailableOnDevice($query, ?int $posDeviceId)
    {
        if ($posDeviceId === null) {
            return $query;
        }

        return $query->where(function ($q) use ($posDeviceId) {
            $q->whereDoesntHave('posDevices')
                ->orWhereHas('posDevices', fn ($q2) => $q2->where('pos_devices.id', $posDeviceId));
        });
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

    /**
     * Check whether an amount in øre meets this method's minimum (when set).
     */
    public function meetsMinimumAmount(int $amountInOre): bool
    {
        if ($this->minimum_amount_kroner === null) {
            return true;
        }

        return $amountInOre >= $this->minimum_amount_kroner * 100;
    }

    /**
     * Whether this payment method is available on the given POS device.
     * When no devices are restricted (empty), available on all.
     */
    public function isAvailableOnDevice(?int $posDeviceId): bool
    {
        if ($posDeviceId === null) {
            return true;
        }

        $deviceIds = $this->posDevices()->pluck('id');

        return $deviceIds->isEmpty() || $deviceIds->contains($posDeviceId);
    }
}
