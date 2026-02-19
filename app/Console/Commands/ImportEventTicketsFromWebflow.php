<?php

namespace App\Console\Commands;

use App\Actions\EventTickets\ImportEventTicketsFromWebflowCollection;
use App\Models\Store;
use Illuminate\Console\Command;
use Positiv\FilamentWebflow\Models\WebflowCollection;

class ImportEventTicketsFromWebflow extends Command
{
    protected $signature = 'event-tickets:import-from-webflow
                            {store : Store ID or slug}
                            {--collection= : Webflow collection ID (optional, uses first active collection if not set)}
                            {--pull : Run pull job first to sync items from Webflow}';

    protected $description = 'Import or update EventTicket records from a Webflow CMS collection (e.g. Halvorsen Arrangementers)';

    public function handle(ImportEventTicketsFromWebflowCollection $import): int
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
        $collection = null;
        if ($collectionId) {
            $collection = WebflowCollection::query()
                ->where('webflow_collection_id', $collectionId)
                ->whereHas('site.addon', fn ($q) => $q->where('store_id', $store->id))
                ->first();
            if (! $collection) {
                $this->error('Webflow collection not found for this store: '.$collectionId);

                return self::FAILURE;
            }
        }

        $pullFirst = (bool) $this->option('pull');
        if ($pullFirst) {
            $this->info('Pulling items from Webflow...');
        }

        $result = $import($store, $collection, $pullFirst);

        if ($pullFirst) {
            $this->info('Pull complete.');
        }

        $this->info("Done. Created: {$result['created']}, Updated: {$result['updated']}.");

        return self::SUCCESS;
    }
}
