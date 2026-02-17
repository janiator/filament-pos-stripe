<?php

namespace App\Models;

use App\Enums\AddonType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Addon extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AddonType::class,
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function webflowSites(): HasMany
    {
        return $this->hasMany(\Positiv\FilamentWebflow\Models\WebflowSite::class, 'addon_id');
    }
}
