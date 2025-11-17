<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TerminalReader extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'terminal_location_id',
        'stripe_reader_id',
        'label',
        'tap_to_pay',
        'device_type',
        'status',
    ];

    protected $casts = [
        'tap_to_pay' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function terminalLocation()
    {
        return $this->belongsTo(TerminalLocation::class);
    }
}
