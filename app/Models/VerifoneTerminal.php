<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerifoneTerminal extends Model
{
    /** @use HasFactory<\Database\Factories\VerifoneTerminalFactory> */
    use HasFactory;

    protected $fillable = [
        'store_id',
        'pos_device_id',
        'terminal_identifier',
        'display_name',
        'sale_id',
        'operator_id',
        'site_entity_id',
        'is_active',
        'terminal_metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'terminal_metadata' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VerifoneTerminalPayment::class);
    }
}
