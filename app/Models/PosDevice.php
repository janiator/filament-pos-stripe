<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'device_identifier',
        'device_name',
        'platform',
        'device_model',
        'device_brand',
        'device_manufacturer',
        'device_product',
        'device_hardware',
        'machine_identifier',
        'system_name',
        'system_version',
        'vendor_identifier',
        'android_id',
        'serial_number',
        'device_status',
        'last_seen_at',
        'device_metadata',
    ];

    protected $casts = [
        'device_metadata' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get Stripe Terminal locations associated with this POS device
     */
    public function terminalLocations(): HasMany
    {
        return $this->hasMany(TerminalLocation::class);
    }
}
