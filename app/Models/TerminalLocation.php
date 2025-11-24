<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TerminalLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'pos_device_id',
        'stripe_location_id',
        'display_name',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    protected static function booted(): void
    {
        static::saved(function (TerminalLocation $location) {
            // Use saved event to ensure it fires for both create and update
            // Only sync on update (not create)
            if ($location->wasRecentlyCreated) {
                return;
            }
            
            $listener = new \App\Listeners\SyncTerminalLocationToStripeListener();
            $listener->handle($location);
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the POS device this terminal location is associated with (optional)
     */
    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }

    public function terminalReaders(): HasMany
    {
        return $this->hasMany(TerminalReader::class);
    }
}
