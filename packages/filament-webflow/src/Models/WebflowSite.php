<?php

namespace Positiv\FilamentWebflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class WebflowSite extends Model
{
    protected $table = 'webflow_sites';

    protected $fillable = [
        'addon_id',
        'webflow_site_id',
        'api_token',
        'name',
        'domain',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'api_token' => 'encrypted',
    ];

    protected $hidden = [
        'api_token',
    ];

    public function addon(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Addon::class);
    }

    /** Store (tenant) via addon – for backward compatibility and Filament tenant scoping. */
    public function store(): HasOneThrough
    {
        return $this->hasOneThrough(\App\Models\Store::class, \App\Models\Addon::class, 'id', 'id', 'addon_id', 'store_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(WebflowCollection::class, 'webflow_site_id', 'id');
    }
}
