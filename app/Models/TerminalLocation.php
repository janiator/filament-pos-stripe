<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stripe\Terminal\Location as StripeLocation;

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

        static::deleting(function (TerminalLocation $location): void {
            // Delete all readers first (triggers TerminalReader deleting → Stripe delete)
            foreach ($location->terminalReaders as $reader) {
                $reader->delete();
            }
            if (empty($location->stripe_location_id)) {
                return;
            }
            $store = $location->store;
            if (! $store || ! $store->hasStripeAccount()) {
                return;
            }
            try {
                StripeLocation::retrieve(
                    $location->stripe_location_id,
                    $store->stripeAccountOptions([], true)
                )->delete();
            } catch (\Throwable) {
                // DB delete still proceeds if Stripe fails (e.g. already deleted)
            }
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
