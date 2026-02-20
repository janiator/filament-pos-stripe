<?php

namespace Positiv\FilamentWebflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Positiv\FilamentWebflow\Support\WebflowFieldData;
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

    /**
     * Default disk for Webflow CMS media. Files are stored under storage/app/public
     * and served at /storage/... (run `php artisan storage:link` if needed).
     */
    public const MEDIA_DISK = 'public';

    /**
     * Download image URLs from field_data into the media library for Image/MultiImage schema fields.
     * Saves to the public disk so images are accessible at /storage/...
     * Call after pull/sync so the edit form can show existing images.
     * Requires the queue worker to be running when using Pull from Webflow.
     */
    public function syncMediaFromFieldData(): void
    {
        $collection = $this->collection;
        if (! $collection instanceof WebflowCollection) {
            return;
        }

        $fieldData = $this->field_data ?? [];
        $schema = $collection->schema ?? [];
        if ($schema === []) {
            Log::debug('WebflowItem: no schema on collection', [
                'webflow_item_id' => $this->id,
                'collection_id' => $collection->id,
            ]);

            return;
        }

        foreach ($schema as $field) {
            $type = $field['type'] ?? null;
            if ($type !== 'Image' && $type !== 'MultiImage') {
                continue;
            }
            $slug = $field['slug'] ?? null;
            if (! is_string($slug)) {
                continue;
            }

            $value = $fieldData[$slug] ?? null;
            $urls = WebflowFieldData::extractImageUrls($value);
            if ($urls === []) {
                $this->clearMediaCollection($slug);

                continue;
            }

            Log::info('WebflowItem: syncing media from field_data', [
                'webflow_item_id' => $this->id,
                'slug' => $slug,
                'url_count' => count($urls),
            ]);

            $this->clearMediaCollection($slug);
            foreach ($urls as $url) {
                try {
                    $this->addMediaFromUrl($url)
                        ->toMediaCollection($slug, self::MEDIA_DISK);
                } catch (\Throwable $e) {
                    Log::warning('WebflowItem: failed to download image for media', [
                        'webflow_item_id' => $this->id,
                        'slug' => $slug,
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
