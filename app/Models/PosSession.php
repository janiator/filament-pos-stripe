<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::observe(\App\Observers\PosSessionObserver::class);
    }

    protected $fillable = [
        'store_id',
        'pos_device_id',
        'user_id',
        'session_number',
        'status',
        'opened_at',
        'closed_at',
        'opening_balance',
        'expected_cash',
        'actual_cash',
        'cash_difference',
        'opening_notes',
        'closing_notes',
        'opening_data',
        'closing_data',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_balance' => 'integer',
        'expected_cash' => 'integer',
        'actual_cash' => 'integer',
        'cash_difference' => 'integer',
        'opening_data' => 'array',
        'closing_data' => 'array',
    ];

    /**
     * Get the store that owns this session
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the POS device for this session
     */
    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }

    /**
     * Get the user who opened this session
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all charges for this session
     */
    public function charges(): HasMany
    {
        return $this->hasMany(ConnectedCharge::class, 'pos_session_id');
    }

    /**
     * Get all events for this session
     */
    public function events(): HasMany
    {
        return $this->hasMany(PosEvent::class);
    }

    /**
     * Get all receipts for this session
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Calculate expected cash from charges
     */
    public function calculateExpectedCash(): int
    {
        return $this->charges()
            ->where('status', 'succeeded')
            ->where('payment_method', 'cash')
            ->sum('amount') ?? 0;
    }

    /**
     * Get total transaction count
     */
    public function getTransactionCountAttribute(): int
    {
        return $this->charges()->where('status', 'succeeded')->count();
    }

    /**
     * Get total amount for this session
     */
    public function getTotalAmountAttribute(): int
    {
        return $this->charges()
            ->where('status', 'succeeded')
            ->sum('amount') ?? 0;
    }

    /**
     * Check if session can be closed
     */
    public function canBeClosed(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Close the session
     */
    public function close(?int $actualCash = null, ?string $notes = null): bool
    {
        if (!$this->canBeClosed()) {
            return false;
        }

        $this->expected_cash = $this->calculateExpectedCash();
        $this->actual_cash = $actualCash;
        $this->cash_difference = $actualCash !== null ? ($actualCash - $this->expected_cash) : null;
        $this->closing_notes = $notes;
        $this->status = 'closed';
        $this->closed_at = now();

        return $this->save();
    }
}
