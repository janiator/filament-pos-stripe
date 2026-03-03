<?php

namespace Positiv\FilamentWebflow\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Models\WebflowItem;
use Positiv\FilamentWebflow\Services\WebflowApiClient;

class PullWebflowItems implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public WebflowCollection $collection
    ) {}

    public function handle(): void
    {
        $collection = WebflowCollection::with('site')->find($this->collection->id);
        if (! $collection) {
            Log::warning('PullWebflowItems: Collection no longer exists', ['collection_id' => $this->collection->id]);

            return;
        }

        $site = $collection->site;
        if (! $site || ! $site->api_token) {
            Log::warning('PullWebflowItems: Site or API token missing', [
                'collection_id' => $collection->id,
                'webflow_site_id' => $collection->webflow_site_id,
            ]);

            return;
        }

        $client = WebflowApiClient::forToken($site->api_token);
        $batchSize = config('filament-webflow.sync.pull_batch_size', 100);
        $offset = 0;
        $total = 0;

        do {
            try {
                $response = $client->listItems($collection->webflow_collection_id, $offset, $batchSize);
            } catch (\Throwable $e) {
                Log::error('PullWebflowItems: Webflow API listItems failed', [
                    'collection_id' => $collection->id,
                    'webflow_collection_id' => $collection->webflow_collection_id,
                    'offset' => $offset,
                    'message' => $e->getMessage(),
                ]);
                throw $e;
            }

            $items = $response['items'] ?? [];
            if (! is_array($items)) {
                break;
            }

            foreach ($items as $item) {
                $this->upsertItem($item, $collection);
                $total++;
            }

            $offset += $batchSize;
        } while (count($items) === $batchSize);

        $collection->update(['last_synced_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function upsertItem(array $item, WebflowCollection $collection): void
    {
        $webflowItemId = $item['id'] ?? null;
        if (! $webflowItemId) {
            return;
        }

        $fieldData = $item['fieldData'] ?? $item['field_data'] ?? [];
        if (! is_array($fieldData)) {
            $fieldData = [];
        }

        $webflowItem = WebflowItem::updateOrCreate(
            [
                'webflow_collection_id' => $collection->id,
                'webflow_item_id' => $webflowItemId,
            ],
            [
                'field_data' => $fieldData,
                'is_published' => isset($item['lastPublished']) && $item['lastPublished'] !== null,
                'is_archived' => $item['isArchived'] ?? false,
                'is_draft' => $item['isDraft'] ?? false,
                'webflow_created_at' => isset($item['createdOn']) ? \Carbon\Carbon::parse($item['createdOn']) : null,
                'webflow_updated_at' => isset($item['lastUpdated']) ? \Carbon\Carbon::parse($item['lastUpdated']) : null,
                'last_synced_at' => now(),
            ]
        );

        $webflowItem->syncMediaFromFieldData();
    }
}
