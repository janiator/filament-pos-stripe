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
        'transaction_count',
        'total_amount',
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
        'transaction_count' => 'integer',
        'total_amount' => 'integer',
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
     * Note: The value is now stored in the database column 'transaction_count'
     * This accessor is kept for backward compatibility but will use the database value
     */
    public function getTransactionCountAttribute(): int
    {
        // Use the database column value if it exists, otherwise calculate from charges
        if (isset($this->attributes['transaction_count'])) {
            return $this->attributes['transaction_count'];
        }
        return $this->charges()->where('status', 'succeeded')->count();
    }

    /**
     * Get total amount for this session
     * Note: The value is now stored in the database column 'total_amount'
     * This accessor is kept for backward compatibility but will use the database value
     */
    public function getTotalAmountAttribute(): int
    {
        // Use the database column value if it exists, otherwise calculate from charges
        if (isset($this->attributes['total_amount'])) {
            return $this->attributes['total_amount'];
        }
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
