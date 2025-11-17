<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TerminalLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'stripe_location_id',
        'display_name',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function terminalReaders()
    {
        return $this->hasMany(TerminalReader::class);
    }
}
