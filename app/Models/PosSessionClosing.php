<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSessionClosing extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'closing_date',
        'closed_by_user_id',
        'closed_at',
        'total_sessions',
        'total_transactions',
        'total_amount',
        'total_cash',
        'total_card',
        'total_refunds',
        'currency',
        'summary_data',
        'notes',
        'verified',
        'verified_by_user_id',
        'verified_at',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'closed_at' => 'datetime',
        'verified_at' => 'datetime',
        'total_sessions' => 'integer',
        'total_transactions' => 'integer',
        'total_amount' => 'integer',
        'total_cash' => 'integer',
        'total_card' => 'integer',
        'total_refunds' => 'integer',
        'summary_data' => 'array',
        'verified' => 'boolean',
    ];

    /**
     * Get the store for this closing
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the user who created this closing
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * Get the user who verified this closing
     */
    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * Get sessions for this closing date
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(PosSession::class, 'store_id', 'store_id')
            ->whereDate('closed_at', $this->closing_date)
            ->where('status', 'closed');
    }
}
