<?php

namespace App\Actions\EventTickets;

use App\Models\EventTicket;
use App\Models\Store;
use Positiv\FilamentWebflow\Jobs\PullWebflowItems;
use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Models\WebflowItem;

class ImportEventTicketsFromWebflowCollection
{
    /**
     * Import or update EventTicket records from a Webflow collection.
     *
     * @return array{created: int, updated: int}
     */
    public function __invoke(Store $store, ?WebflowCollection $collection = null, bool $pullFirst = false): array
    {
        if ($collection === null) {
            $collection = WebflowCollection::query()
                ->where('is_active', true)
                ->whereHas('site', fn ($q) => $q->where('store_id', $store->id))
                ->first();
        }

        if (! $collection) {
            return ['created' => 0, 'updated' => 0];
        }

        if ($pullFirst) {
            PullWebflowItems::dispatchSync($collection);
        }

        $items = WebflowItem::query()
            ->where('webflow_collection_id', $collection->id)
            ->get();

        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $data = MapWebflowItemToEventTicketData::map($item);
            $data['store_id'] = $store->id;
            $data['webflow_item_id'] = $item->id;

            $existing = EventTicket::query()
                ->where('webflow_item_id', $item->id)
                ->first();

            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                EventTicket::create($data);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }
}
