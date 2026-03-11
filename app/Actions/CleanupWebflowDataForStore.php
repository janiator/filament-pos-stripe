<?php

namespace App\Actions;

use App\Models\EventTicket;
use App\Models\Store;
use Positiv\FilamentWebflow\Models\WebflowItem;
use Positiv\FilamentWebflow\Models\WebflowSite;

class CleanupWebflowDataForStore
{
    /**
     * Delete all Webflow sites for the store (cascades to collections and items),
     * and delete EventTickets that referenced items under those sites.
     */
    public function __invoke(Store $store): int
    {
        $siteIds = WebflowSite::query()
            ->where('store_id', $store->id)
            ->pluck('id')
            ->all();

        if (empty($siteIds)) {
            return 0;
        }

        $collectionIds = \Positiv\FilamentWebflow\Models\WebflowCollection::query()
            ->whereIn('webflow_site_id', $siteIds)
            ->pluck('id')
            ->all();

        $webflowItemIds = [];
        if (! empty($collectionIds)) {
            $webflowItemIds = WebflowItem::query()
                ->whereIn('webflow_collection_id', $collectionIds)
                ->pluck('id')
                ->all();
        }

        if (class_exists(EventTicket::class) && ! empty($webflowItemIds)) {
            EventTicket::query()
                ->whereIn('webflow_item_id', $webflowItemIds)
                ->delete();
        }

        $deleted = WebflowSite::query()
            ->where('store_id', $store->id)
            ->delete();

        return $deleted;
    }
}
