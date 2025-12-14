<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectedCharge extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::observe(\App\Observers\ConnectedChargeObserver::class);
    }

    protected $fillable = [
        'stripe_charge_id',
        'stripe_account_id',
        'pos_session_id',
        'stripe_customer_id',
        'stripe_payment_intent_id',
        'amount',
        'amount_refunded',
        'currency',
        'status',
        'payment_method',
        'description',
        'failure_code',
        'failure_message',
        'captured',
        'refunded',
        'paid',
        'paid_at',
        'metadata',
        'outcome',
        'charge_type',
        'application_fee_amount',
        'transaction_code',
        'payment_code',
        'tip_amount',
        'article_group_code',
    ];

    protected $casts = [
        'amount' => 'integer',
        'amount_refunded' => 'integer',
        'application_fee_amount' => 'integer',
        'tip_amount' => 'integer',
        'captured' => 'boolean',
        'refunded' => 'boolean',
        'paid' => 'boolean',
        'paid_at' => 'datetime',
        'metadata' => 'array',
        'outcome' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'stripe_account_id', 'stripe_account_id');
    }

    public function customer(): ?BelongsTo
    {
        if (!class_exists(\App\Models\ConnectedCustomer::class)) {
            return null;
        }
        // We can't use whereColumn in belongsTo with eager loading, so we'll handle the constraint
        // in the eager loading closure or filter after loading
        return $this->belongsTo(\App\Models\ConnectedCustomer::class, 'stripe_customer_id', 'stripe_customer_id');
    }

    /**
     * Get the POS session for this charge
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    /**
     * Get the receipt for this charge
     * For deferred payments: returns sales receipt if paid, otherwise delivery receipt
     * For regular purchases: returns sales receipt
     * Prioritizes sales receipts over delivery receipts, then returns the latest
     */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class, 'charge_id')
            ->orderByRaw("CASE WHEN receipt_type = 'sales' THEN 0 ELSE 1 END") // Prioritize sales receipts first
            ->orderByDesc('created_at'); // Then get latest if multiple sales receipts exist
    }

    /**
     * Get all receipts for this charge
     * Returns all receipt types (sales, return, copy, delivery, etc.) associated with this charge
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'charge_id')
            ->orderByRaw("CASE WHEN receipt_type = 'sales' THEN 0 ELSE 1 END") // Prioritize sales receipts first
            ->orderByDesc('created_at'); // Then order by creation date
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2) . ' ' . strtoupper($this->currency);
    }

    public function getFormattedAmountRefundedAttribute(): string
    {
        return number_format($this->amount_refunded / 100, 2) . ' ' . strtoupper($this->currency);
    }

    public function getFormattedNetAmountAttribute(): string
    {
        $net = $this->amount - $this->amount_refunded - ($this->application_fee_amount ?? 0);
        return number_format($net / 100, 2) . ' ' . strtoupper($this->currency);
    }

}
