<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectedTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_transfer_id',
        'stripe_account_id',
        'stripe_charge_id',
        'stripe_payment_intent_id',
        'amount',
        'currency',
        'status',
        'destination',
        'description',
        'arrival_date',
        'metadata',
        'reversals',
        'reversed_amount',
    ];

    protected $casts = [
        'amount' => 'integer',
        'reversed_amount' => 'integer',
        'arrival_date' => 'datetime',
        'metadata' => 'array',
        'reversals' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'stripe_account_id', 'stripe_account_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(ConnectedCharge::class, 'stripe_charge_id', 'stripe_charge_id')
            ->where('stripe_account_id', $this->stripe_account_id);
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2) . ' ' . strtoupper($this->currency);
    }

    public function getFormattedReversedAmountAttribute(): string
    {
        return number_format($this->reversed_amount / 100, 2) . ' ' . strtoupper($this->currency);
    }

    public function getFormattedNetAmountAttribute(): string
    {
        $net = $this->amount - $this->reversed_amount;
        return number_format($net / 100, 2) . ' ' . strtoupper($this->currency);
    }

    public function team()
    {
        return $this->store?->team;
    }
}
