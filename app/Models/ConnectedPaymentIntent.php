<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectedPaymentIntent extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_id',
        'stripe_account_id',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'amount',
        'currency',
        'status',
        'capture_method',
        'confirmation_method',
        'description',
        'receipt_email',
        'statement_descriptor',
        'statement_descriptor_suffix',
        'metadata',
        'payment_method_options',
        'client_secret',
        'canceled_at',
        'cancellation_reason',
        'succeeded_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'payment_method_options' => 'array',
        'canceled_at' => 'datetime',
        'succeeded_at' => 'datetime',
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
        return $this->belongsTo(\App\Models\ConnectedCustomer::class, 'stripe_customer_id', 'stripe_customer_id');
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2) . ' ' . strtoupper($this->currency);
    }
}
