<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class GiftCard extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'code',
        'pin',
        'initial_amount',
        'balance',
        'amount_redeemed',
        'currency',
        'status',
        'purchased_at',
        'expires_at',
        'last_used_at',
        'purchase_charge_id',
        'purchased_by_user_id',
        'customer_id',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'initial_amount' => 'integer',
        'balance' => 'integer',
        'amount_redeemed' => 'integer',
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        // Hash PIN if provided
        static::saving(function (GiftCard $giftCard) {
            if ($giftCard->isDirty('pin') && $giftCard->pin) {
                $giftCard->pin = Hash::make($giftCard->pin);
            }
        });
    }

    /**
     * Get the store that owns this gift card
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the charge for the original purchase
     */
    public function purchaseCharge(): BelongsTo
    {
        return $this->belongsTo(ConnectedCharge::class, 'purchase_charge_id');
    }

    /**
     * Get the user who sold/purchased the gift card
     */
    public function purchasedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by_user_id');
    }

    /**
     * Get the customer who purchased the gift card (optional)
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(ConnectedCustomer::class, 'customer_id');
    }

    /**
     * Get all transactions for this gift card
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class)->orderBy('created_at', 'desc');
    }

    /**
     * Check if gift card is valid (can be used)
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->balance <= 0) {
            return false;
        }

        if ($this->expires_at && Carbon::now()->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a specific amount can be redeemed
     */
    public function canRedeem(int $amount): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        return $this->balance >= $amount;
    }

    /**
     * Verify PIN (if PIN is enabled)
     */
    public function verifyPin(?string $pin): bool
    {
        if (!$this->pin) {
            return true; // No PIN required
        }

        if (!$pin) {
            return false; // PIN required but not provided
        }

        return Hash::check($pin, $this->pin);
    }

    /**
     * Get formatted balance
     */
    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance / 100, 2, '.', '') . ' ' . strtoupper($this->currency);
    }

    /**
     * Get formatted initial amount
     */
    public function getFormattedInitialAmountAttribute(): string
    {
        return number_format($this->initial_amount / 100, 2, '.', '') . ' ' . strtoupper($this->currency);
    }

    /**
     * Scope: Active gift cards
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Redeemed gift cards
     */
    public function scopeRedeemed($query)
    {
        return $query->where('status', 'redeemed');
    }

    /**
     * Scope: Expired gift cards
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
            ->orWhere(function ($q) {
                $q->where('status', 'active')
                  ->where('expires_at', '<', Carbon::now());
            });
    }

    /**
     * Scope: Voided gift cards
     */
    public function scopeVoided($query)
    {
        return $query->where('status', 'voided');
    }

    /**
     * Scope: Search by code
     */
    public function scopeSearchByCode($query, string $code)
    {
        return $query->where('code', 'like', "%{$code}%");
    }

    /**
     * Scope: For store
     */
    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Generate a unique gift card code
     */
    public static function generateCode(string $prefix = 'GC-'): string
    {
        do {
            // Generate 12 alphanumeric characters
            $random = strtoupper(bin2hex(random_bytes(6)));
            $code = $prefix . $random;
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
