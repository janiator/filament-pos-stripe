<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuantityUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'stripe_account_id',
        'name',
        'symbol',
        'description',
        'is_standard',
        'active',
    ];

    protected $casts = [
        'is_standard' => 'boolean',
        'active' => 'boolean',
    ];

    /**
     * Get the store that owns this quantity unit
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the products using this quantity unit
     */
    public function products(): HasMany
    {
        return $this->hasMany(ConnectedProduct::class);
    }

    /**
     * Get display name (name with symbol)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name . ($this->symbol ? ' (' . $this->symbol . ')' : '');
    }
}
