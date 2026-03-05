<?php

namespace App\Observers;

use App\Models\EventTicket;
use Positiv\FilamentWebflow\Models\WebflowSite;

class WebflowSiteObserver
{
    /**
     * Before a Webflow site is deleted, delete EventTicket records that reference
     * items belonging to this site (so we do not leave orphaned tickets with null webflow_item_id).
     */
    public function deleting(WebflowSite $site): void
    {
        if (! class_exists(EventTicket::class)) {
            return;
        }

        $itemIds = $site->collections()->pluck('id')->toArray();
        if (empty($itemIds)) {
            return;
        }

        $webflowItemIds = \Positiv\FilamentWebflow\Models\WebflowItem::query()
            ->whereIn('webflow_collection_id', $itemIds)
            ->pluck('id')
            ->all();

        if (! empty($webflowItemIds)) {
            EventTicket::query()
                ->whereIn('webflow_item_id', $webflowItemIds)
                ->delete();
        }
    }
}
