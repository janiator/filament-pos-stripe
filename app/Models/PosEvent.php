<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'pos_device_id',
        'pos_session_id',
        'user_id',
        'related_charge_id',
        'event_code',
        'event_type',
        'description',
        'event_data',
        'occurred_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Event code constants (PredefinedBasicID-13)
     */
    public const EVENT_APPLICATION_START = '13001';
    public const EVENT_APPLICATION_SHUTDOWN = '13002';
    public const EVENT_EMPLOYEE_LOGIN = '13003';
    public const EVENT_EMPLOYEE_LOGOUT = '13004';
    public const EVENT_CASH_DRAWER_OPEN = '13005';
    public const EVENT_CASH_DRAWER_CLOSE = '13006';
    public const EVENT_X_REPORT = '13008';
    public const EVENT_Z_REPORT = '13009';
    public const EVENT_SALES_RECEIPT = '13012';
    public const EVENT_RETURN_RECEIPT = '13013';
    public const EVENT_VOID_TRANSACTION = '13014';
    public const EVENT_CORRECTION_RECEIPT = '13015';
    public const EVENT_CASH_PAYMENT = '13016';
    public const EVENT_CARD_PAYMENT = '13017';
    public const EVENT_MOBILE_PAYMENT = '13018';
    public const EVENT_OTHER_PAYMENT = '13019';
    public const EVENT_SESSION_OPENED = '13020';
    public const EVENT_SESSION_CLOSED = '13021';

    /**
     * Get the store for this event
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the POS device for this event
     */
    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }

    /**
     * Get the POS session for this event
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    /**
     * Get the user who triggered this event
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related charge (for transaction events)
     */
    public function relatedCharge(): BelongsTo
    {
        return $this->belongsTo(ConnectedCharge::class, 'related_charge_id');
    }

    /**
     * Get event code description
     */
    public function getEventDescriptionAttribute(): string
    {
        return match($this->event_code) {
            self::EVENT_APPLICATION_START => 'POS application start',
            self::EVENT_APPLICATION_SHUTDOWN => 'POS application shut down',
            self::EVENT_EMPLOYEE_LOGIN => 'Employee log in',
            self::EVENT_EMPLOYEE_LOGOUT => 'Employee log out',
            self::EVENT_CASH_DRAWER_OPEN => 'Open cash drawer',
            self::EVENT_CASH_DRAWER_CLOSE => 'Close cash drawer',
            self::EVENT_X_REPORT => 'X report (daily sales report)',
            self::EVENT_Z_REPORT => 'Z report (end-of-day report)',
            self::EVENT_SALES_RECEIPT => 'Sales receipt',
            self::EVENT_RETURN_RECEIPT => 'Return receipt',
            self::EVENT_VOID_TRANSACTION => 'Void transaction',
            self::EVENT_CORRECTION_RECEIPT => 'Correction receipt',
            self::EVENT_CASH_PAYMENT => 'Cash payment',
            self::EVENT_CARD_PAYMENT => 'Card payment',
            self::EVENT_MOBILE_PAYMENT => 'Mobile payment',
            self::EVENT_OTHER_PAYMENT => 'Other payment method',
            self::EVENT_SESSION_OPENED => 'Session opened',
            self::EVENT_SESSION_CLOSED => 'Session closed',
            default => 'Unknown event',
        };
    }

    /**
     * Scope: Filter by event code
     */
    public function scopeWithEventCode($query, string $eventCode)
    {
        return $query->where('event_code', $eventCode);
    }

    /**
     * Scope: Filter by event type
     */
    public function scopeWithEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeInDateRange($query, $fromDate, $toDate)
    {
        return $query->whereBetween('occurred_at', [$fromDate, $toDate]);
    }

    /**
     * Scope: Filter by session
     */
    public function scopeForSession($query, int $sessionId)
    {
        return $query->where('pos_session_id', $sessionId);
    }
}
