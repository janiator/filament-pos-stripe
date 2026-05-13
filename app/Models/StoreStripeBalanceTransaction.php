<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreStripeBalanceTransaction extends Model
{
    protected $fillable = [
        'store_id',
        'stripe_account_id',
        'stripe_balance_transaction_id',
        'type',
        'amount',
        'fee',
        'net',
        'currency',
        'status',
        'description',
        'stripe_charge_id',
        'stripe_payment_intent_id',
        'stripe_payout_id',
        'fee_details',
        'source_metadata',
        'stripe_created',
        'available_on',
        'reporting_category',
    ];

    protected $casts = [
        'amount' => 'integer',
        'fee' => 'integer',
        'net' => 'integer',
        'fee_details' => 'array',
        'source_metadata' => 'array',
        'stripe_created' => 'integer',
        'available_on' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Stripe balance transaction types that represent a settled card/wallet payment with a {@code ch_} or {@code py_} source id.
     */
    public function isPayoutSaleSourceMirrorRow(): bool
    {
        return in_array((string) $this->type, ['charge', 'payment'], true)
            && filled($this->stripe_charge_id);
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2).' '.strtoupper((string) $this->currency);
    }

    public function getFormattedFeeAttribute(): string
    {
        return number_format($this->fee / 100, 2).' '.strtoupper((string) $this->currency);
    }

    public function getFormattedNetAttribute(): string
    {
        return number_format($this->net / 100, 2).' '.strtoupper((string) $this->currency);
    }
}
