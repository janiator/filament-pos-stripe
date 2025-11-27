<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptPrinter extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'pos_device_id',
        'name',
        'printer_type',
        'printer_model',
        'paper_width',
        'connection_type',
        'ip_address',
        'port',
        'device_id',
        'use_https',
        'timeout',
        'is_active',
        'monitor_status',
        'drawer_open_level',
        'use_job_id',
        'printer_metadata',
        'last_used_at',
    ];

    protected $casts = [
        'use_https' => 'boolean',
        'is_active' => 'boolean',
        'monitor_status' => 'boolean',
        'use_job_id' => 'boolean',
        'printer_metadata' => 'array',
        'last_used_at' => 'datetime',
        'port' => 'integer',
        'timeout' => 'integer',
    ];

    /**
     * Get the store that owns this printer
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the POS device this printer is associated with (optional)
     */
    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }

    /**
     * Get the ePOS-Print service URL
     */
    public function getEposUrlAttribute(): string
    {
        $protocol = $this->use_https ? 'https' : 'http';
        return "{$protocol}://{$this->ip_address}/cgi-bin/epos/service.cgi?devid={$this->device_id}&timeout={$this->timeout}";
    }

    /**
     * Check if printer is configured for network connection
     */
    public function isNetworkPrinter(): bool
    {
        return $this->connection_type === 'network' && !empty($this->ip_address);
    }
}
