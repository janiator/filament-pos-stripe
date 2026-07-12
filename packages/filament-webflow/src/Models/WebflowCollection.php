<?php

namespace Positiv\FilamentWebflow\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'use_for_event_tickets',
        'last_synced_at',
    ];

    protected $casts = [
        'schema' => 'array',
        'field_mapping' => 'array',
        'is_active' => 'boolean',
        'use_for_event_tickets' => 'boolean',
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

    /**
     * Limit collections whose Webflow site belongs to the given store (tenant).
     *
     * Webflow sites are scoped by {@see WebflowSite::$store_id}; legacy per-addon linkage on
     * the site record was removed in favor of {@see WebflowSite::store()}.
     *
     * @param  Builder<WebflowCollection>  $query
     * @return Builder<WebflowCollection>
     */
    public function scopeForSiteOnStore(Builder $query, int|string|null $storeKey): Builder
    {
        if ($storeKey === null || $storeKey === '') {
            return $query;
        }

        return $query->whereHas(
            'site',
            fn (Builder $siteQuery): Builder => $siteQuery->where('store_id', $storeKey),
        );
    }
}
