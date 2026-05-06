<?php

namespace App\Models;

use App\Enums\TripletexSyncType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StoreStripePayout extends Model
{
    protected static function booted(): void
    {
        static::observe(\App\Observers\StoreStripePayoutObserver::class);
    }

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

    /**
     * @return HasMany<TripletexSyncRun, $this>
     */
    public function tripletexSyncRuns(): HasMany
    {
        return $this->hasMany(TripletexSyncRun::class, 'store_stripe_payout_id');
    }

    /**
     * Latest Tripletex voucher sync attempt for this payout row.
     *
     * @return HasOne<TripletexSyncRun, $this>
     */
    public function latestTripletexSyncRun(): HasOne
    {
        return $this->hasOne(TripletexSyncRun::class, 'store_stripe_payout_id')
            ->where('sync_type', TripletexSyncType::Payout)
            ->latestOfMany();
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2).' '.strtoupper((string) $this->currency);
    }
}
