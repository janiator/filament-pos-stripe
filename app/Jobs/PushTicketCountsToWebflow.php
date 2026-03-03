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

        // Map EventTicket fields to Webflow CMS field slugs (Halvorsen Arrangementers collection)
        $updates = array_filter([
            'billett-1-tilgjengelig' => $this->eventTicket->ticket_1_available,
            'billett-1-solgte' => $this->eventTicket->ticket_1_sold,
            'billett-2-tilgjengelig' => $this->eventTicket->ticket_2_available,
            'billett-2-solgte' => $this->eventTicket->ticket_2_sold,
            'utsolgt' => $this->eventTicket->is_sold_out,
        ], fn ($v) => $v !== null);

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
