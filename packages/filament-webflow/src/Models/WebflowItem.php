<?php

namespace Positiv\FilamentWebflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class WebflowItem extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'webflow_items';

    protected $fillable = [
        'webflow_collection_id',
        'webflow_item_id',
        'field_data',
        'is_published',
        'is_archived',
        'is_draft',
        'webflow_created_at',
        'webflow_updated_at',
        'last_synced_at',
    ];

    protected $casts = [
        'field_data' => 'array',
        'is_published' => 'boolean',
        'is_archived' => 'boolean',
        'is_draft' => 'boolean',
        'webflow_created_at' => 'datetime',
        'webflow_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(WebflowCollection::class, 'webflow_collection_id');
    }
}
