<?php

namespace Positiv\FilamentWebflow\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Positiv\FilamentWebflow\Models\WebflowItem;
use Positiv\FilamentWebflow\Services\WebflowApiClient;

class PushWebflowItem implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public WebflowItem $item,
        public bool $publish = false
    ) {}

    public function handle(): void
    {
        $collection = $this->item->collection;
        $site = $collection?->site;
        if (! $site || ! $site->api_token) {
            Log::warning('PushWebflowItem: Site or API token missing', [
                'item_id' => $this->item->id,
            ]);

            return;
        }

        $client = WebflowApiClient::forToken($site->api_token);
        $collectionId = $collection->webflow_collection_id;
        $payload = [
            'isArchived' => $this->item->is_archived,
            'isDraft' => $this->item->is_draft,
            'fieldData' => $this->item->field_data ?? [],
        ];

        if ($this->item->webflow_item_id) {
            $response = $client->updateItem($collectionId, $this->item->webflow_item_id, $payload);
        } else {
            $response = $client->createItem($collectionId, $payload);
            $this->item->webflow_item_id = $response['id'] ?? null;
            $this->item->save();
        }

        if ($this->publish && ($this->item->webflow_item_id ?? null)) {
            $client->publishItems($collectionId, [$this->item->webflow_item_id]);
            $this->item->update(['is_published' => true]);
        }

        $this->item->update([
            'last_synced_at' => now(),
            'webflow_updated_at' => now(),
        ]);
    }
}
