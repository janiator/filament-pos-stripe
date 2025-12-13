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
        'default_printer_id',
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

    /**
     * Get receipt printers associated with this POS device
     */
    public function receiptPrinters(): HasMany
    {
        return $this->hasMany(ReceiptPrinter::class);
    }

    /**
     * Get the default receipt printer for this POS device
     */
    public function defaultPrinter(): BelongsTo
    {
        return $this->belongsTo(ReceiptPrinter::class, 'default_printer_id');
    }

    /**
     * Get all POS sessions for this device
     */
    public function posSessions(): HasMany
    {
        return $this->hasMany(PosSession::class);
    }
}
