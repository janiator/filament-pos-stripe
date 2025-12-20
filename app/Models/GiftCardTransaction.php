<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'gift_card_id',
        'store_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'charge_id',
        'pos_session_id',
        'pos_event_id',
        'user_id',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Transaction types
     */
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_REDEMPTION = 'redemption';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_VOID = 'void';

    /**
     * Get the gift card for this transaction
     */
    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    /**
     * Get the store for this transaction
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the related charge
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(ConnectedCharge::class, 'charge_id');
    }

    /**
     * Get the POS session for this transaction
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    /**
     * Get the POS event for this transaction
     */
    public function posEvent(): BelongsTo
    {
        return $this->belongsTo(PosEvent::class);
    }

    /**
     * Get the user who performed this transaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->amount < 0 ? '-' : '+';
        $absAmount = abs($this->amount);
        return $sign . number_format($absAmount / 100, 2, '.', '') . ' NOK';
    }

    /**
     * Scope: Purchase transactions
     */
    public function scopePurchases($query)
    {
        return $query->where('type', self::TYPE_PURCHASE);
    }

    /**
     * Scope: Redemption transactions
     */
    public function scopeRedemptions($query)
    {
        return $query->where('type', self::TYPE_REDEMPTION);
    }

    /**
     * Scope: Refund transactions
     */
    public function scopeRefunds($query)
    {
        return $query->where('type', self::TYPE_REFUND);
    }

    /**
     * Scope: Adjustment transactions
     */
    public function scopeAdjustments($query)
    {
        return $query->where('type', self::TYPE_ADJUSTMENT);
    }

    /**
     * Scope: Void transactions
     */
    public function scopeVoids($query)
    {
        return $query->where('type', self::TYPE_VOID);
    }
}
