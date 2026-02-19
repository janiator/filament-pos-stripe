<?php

namespace Positiv\FilamentWebflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebflowSite extends Model
{
    protected $table = 'webflow_sites';

    protected $fillable = [
        'store_id',
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

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Store::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(WebflowCollection::class, 'webflow_site_id', 'id');
    }
}
