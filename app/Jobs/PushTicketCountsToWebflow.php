<?php

namespace App\Jobs;

use App\Models\EventTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Positiv\FilamentWebflow\Services\WebflowApiClient;
use Positiv\FilamentWebflow\Support\EventTicketFieldMapping;

class PushTicketCountsToWebflow implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public EventTicket $eventTicket
    ) {}

    public function handle(): void
    {
        $item = $this->eventTicket->webflowItem;
        if (! $item) {
            Log::debug('PushTicketCountsToWebflow: EventTicket has no linked Webflow item', [
                'event_ticket_id' => $this->eventTicket->id,
            ]);

            return;
        }

        $collection = $item->collection;
        $site = $collection?->site;
        if (! $site || ! $site->api_token) {
            Log::warning('PushTicketCountsToWebflow: Site or API token missing', [
                'event_ticket_id' => $this->eventTicket->id,
            ]);

            return;
        }

        if (! $item->webflow_item_id) {
            Log::warning('PushTicketCountsToWebflow: Webflow item has no webflow_item_id', [
                'webflow_item_id' => $item->id,
            ]);

            return;
        }

        $client = WebflowApiClient::forToken($site->api_token);
        $collectionId = $collection->webflow_collection_id;

        $slug = fn (string $logicalKey): ?string => EventTicketFieldMapping::resolveSlug($collection, $logicalKey);
        $updates = array_filter([
            $slug('ticket_1_available') => $this->eventTicket->ticket_1_available,
            $slug('ticket_1_sold') => $this->eventTicket->ticket_1_sold,
            $slug('ticket_2_available') => $this->eventTicket->ticket_2_available,
            $slug('ticket_2_sold') => $this->eventTicket->ticket_2_sold,
            $slug('is_sold_out') => $this->eventTicket->is_sold_out,
        ], fn ($v) => $v !== null);
        $updates = array_filter($updates, fn ($_, $key) => $key !== null, ARRAY_FILTER_USE_BOTH);

        $existing = $item->field_data ?? [];
        $fieldData = array_merge($existing, $updates);

        $payload = [
            'fieldData' => $fieldData,
        ];

        $client->updateItem($collectionId, $item->webflow_item_id, $payload);
        $client->publishItems($collectionId, [$item->webflow_item_id]);

        $item->update([
            'field_data' => $payload['fieldData'],
            'last_synced_at' => now(),
            'is_published' => true,
        ]);

        Log::info('PushTicketCountsToWebflow: Updated Webflow CMS item', [
            'event_ticket_id' => $this->eventTicket->id,
            'webflow_item_id' => $item->webflow_item_id,
        ]);
    }
}
