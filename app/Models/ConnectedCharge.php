<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectedCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_charge_id',
        'stripe_account_id',
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
    ];

    protected $casts = [
        'amount' => 'integer',
        'amount_refunded' => 'integer',
        'application_fee_amount' => 'integer',
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
