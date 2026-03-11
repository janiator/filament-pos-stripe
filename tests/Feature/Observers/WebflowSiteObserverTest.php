<?php

use App\Models\EventTicket;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Models\WebflowItem;
use Positiv\FilamentWebflow\Models\WebflowSite;

uses(RefreshDatabase::class);

it('deletes event tickets when webflow site is deleted', function () {
    $store = Store::factory()->create();
    $site = WebflowSite::create([
        'store_id' => $store->id,
        'webflow_site_id' => 'site_'.uniqid(),
        'name' => 'Test',
        'is_active' => true,
    ]);
    $collection = WebflowCollection::create([
        'webflow_site_id' => $site->id,
        'webflow_collection_id' => 'col_'.uniqid(),
        'name' => 'Events',
        'is_active' => true,
    ]);
    $item = WebflowItem::create([
        'webflow_collection_id' => $collection->id,
        'webflow_item_id' => 'item_1',
        'field_data' => [],
    ]);
    $ticket = EventTicket::create([
        'store_id' => $store->id,
        'webflow_item_id' => $item->id,
        'name' => 'Concert',
    ]);

    $site->delete();

    expect(EventTicket::find($ticket->id))->toBeNull();
});
