<?php

use App\Actions\CleanupWebflowDataForStore;
use App\Models\EventTicket;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Models\WebflowItem;
use Positiv\FilamentWebflow\Models\WebflowSite;

uses(RefreshDatabase::class);

it('deletes all webflow sites for store and event tickets for their items', function () {
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
    EventTicket::create([
        'store_id' => $store->id,
        'webflow_item_id' => $item->id,
        'name' => 'Concert',
    ]);

    $cleanup = app(CleanupWebflowDataForStore::class);
    $deleted = $cleanup($store);

    expect($deleted)->toBe(1);
    expect(WebflowSite::where('store_id', $store->id)->count())->toBe(0);
    expect(EventTicket::where('store_id', $store->id)->count())->toBe(0);
});

it('returns zero when store has no webflow sites', function () {
    $store = Store::factory()->create();

    $cleanup = app(CleanupWebflowDataForStore::class);
    $deleted = $cleanup($store);

    expect($deleted)->toBe(0);
});
