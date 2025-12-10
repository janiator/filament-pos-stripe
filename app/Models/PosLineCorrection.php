<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosLineCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'pos_session_id',
        'user_id',
        'correction_type',
        'quantity_reduction',
        'amount_reduction',
        'reason',
        'original_item_data',
        'corrected_item_data',
        'occurred_at',
    ];

    protected $casts = [
        'quantity_reduction' => 'integer',
        'amount_reduction' => 'integer',
        'original_item_data' => 'array',
        'corrected_item_data' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the store that owns this line correction
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the POS session for this line correction
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    /**
     * Get the user who made this correction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
