<?php

namespace Positiv\FilamentWebflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebflowCollection extends Model
{
    protected $table = 'webflow_collections';

    protected $fillable = [
        'webflow_site_id',
        'webflow_collection_id',
        'name',
        'slug',
        'schema',
        'field_mapping',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'schema' => 'array',
        'field_mapping' => 'array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(WebflowSite::class, 'webflow_site_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WebflowItem::class, 'webflow_collection_id');
    }
}
