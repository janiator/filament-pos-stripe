<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stripe\Terminal\Reader as StripeReader;

class TerminalReader extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'terminal_location_id',
        'stripe_reader_id',
        'serial_number',
        'label',
        'tap_to_pay',
        'device_type',
        'status',
    ];

    protected $casts = [
        'tap_to_pay' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (TerminalReader $reader): void {
            if (empty($reader->stripe_reader_id)) {
                return;
            }
            $store = $reader->store;
            if (! $store || ! $store->hasStripeAccount()) {
                return;
            }
            try {
                StripeReader::retrieve(
                    $reader->stripe_reader_id,
                    $store->stripeAccountOptions([], true)
                )->delete();
            } catch (\Throwable) {
                // DB delete still proceeds if Stripe fails (e.g. already deleted)
            }
        });
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function terminalLocation()
    {
        return $this->belongsTo(TerminalLocation::class);
    }
}
