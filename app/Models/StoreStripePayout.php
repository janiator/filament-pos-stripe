<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreStripePayout extends Model
{
    protected $fillable = [
        'store_id',
        'stripe_account_id',
        'stripe_payout_id',
        'amount',
        'currency',
        'status',
        'arrival_date',
        'method',
        'failure_code',
        'failure_message',
        'statement_descriptor',
        'automatic',
        'stripe_created',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'arrival_date' => 'datetime',
        'automatic' => 'boolean',
        'stripe_created' => 'integer',
        'metadata' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2).' '.strtoupper((string) $this->currency);
    }
}
