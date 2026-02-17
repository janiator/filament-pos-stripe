<?php

namespace App\Console\Commands;

use App\Models\EventTicket;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Positiv\FilamentWebflow\Jobs\PullWebflowItems;
use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Models\WebflowItem;

class ImportEventTicketsFromWebflow extends Command
{
    protected $signature = 'event-tickets:import-from-webflow
                            {store : Store ID or slug}
                            {--collection= : Webflow collection ID (optional, uses first active collection if not set)}
                            {--pull : Run pull job first to sync items from Webflow}';

    protected $description = 'Import or update EventTicket records from a Webflow CMS collection (e.g. Halvorsen Arrangementers)';

    public function handle(): int
    {
        $storeInput = $this->argument('store');
        $store = is_numeric($storeInput)
            ? Store::find($storeInput)
            : Store::where('slug', $storeInput)->first();

        if (! $store) {
            $this->error('Store not found: '.$storeInput);

            return self::FAILURE;
        }

        $collectionId = $this->option('collection');
        $collection = $collectionId
            ? WebflowCollection::where('webflow_collection_id', $collectionId)->whereHas('site.addon', fn ($q) => $q->where('store_id', $store->id))->first()
            : WebflowCollection::where('is_active', true)->whereHas('site.addon', fn ($q) => $q->where('store_id', $store->id))->first();

        if (! $collection) {
            $this->error('No Webflow collection found for this store. Connect a site and discover collections in Filament first.');

            return self::FAILURE;
        }

        if ($this->option('pull')) {
            $this->info('Pulling items from Webflow...');
            PullWebflowItems::dispatchSync($collection);
            $this->info('Pull complete.');
        }

        $items = WebflowItem::where('webflow_collection_id', $collection->id)->get();
        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $data = $this->mapWebflowItemToEventTicket($item);
            $data['store_id'] = $store->id;
            $data['webflow_item_id'] = $item->id;

            $existing = EventTicket::where('webflow_item_id', $item->id)->first();
            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                EventTicket::create($data);
                $created++;
            }
        }

        $this->info("Done. Created: {$created}, Updated: {$updated}.");

        return self::SUCCESS;
    }

    private function mapWebflowItemToEventTicket(WebflowItem $item): array
    {
        $fd = $item->field_data ?? [];
        $get = fn ($keys) => $this->getFirstMatch($fd, $keys);

        $eventDate = $get(['datofelt', 'event_date', 'Event Date']);
        if (is_string($eventDate)) {
            try {
                $eventDate = Carbon::parse($eventDate);
            } catch (\Throwable $e) {
                $eventDate = null;
            }
        }

        return [
            'name' => $get(['name', 'Name']) ?? 'Untitled',
            'slug' => $get(['slug', 'Slug']),
            'description' => $get(['beskrivelse', 'description', 'Beskrivelse']),
            'image_url' => $get(['bilde', 'image', 'Bilde']),
            'event_date' => $eventDate,
            'event_time' => $get(['klokkeslett', 'event_time', 'Klokkeslett']),
            'ticket_1_label' => 'Billett 1',
            'ticket_1_available' => $this->intOrNull($get(['billett-1-tilgjengelig', 'Billett 1: Tilgjengelig'])),
            'ticket_1_sold' => (int) ($get(['billett-1-solgte', 'Billett 1: Solgte']) ?? 0),
            'ticket_1_payment_link_id' => $get(['payment-link-id-1', 'Payment link id 1']),
            'ticket_1_price_id' => $get(['price-id-1', 'Price id 1']),
            'ticket_2_label' => 'Billett 2',
            'ticket_2_available' => $this->intOrNull($get(['billett-2-tilgjengelig', 'Billett 2: Tilgjengelig'])),
            'ticket_2_sold' => (int) ($get(['billett-2-solgte', 'Billett 2: Solgte']) ?? 0),
            'ticket_2_payment_link_id' => $get(['payment-link-id-2', 'Payment link id 2']),
            'ticket_2_price_id' => $get(['price-id-2', 'Price id 2']),
            'is_sold_out' => filter_var($get(['utsolgt', 'Utsolgt']), FILTER_VALIDATE_BOOLEAN),
            'is_archived' => $item->is_archived ?? false,
        ];
    }

    private function getFirstMatch(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
