<?php

use App\Actions\EventTickets\ImportEventTicketsFromWebflowCollection;
use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\EventTicket;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Models\WebflowItem;
use Positiv\FilamentWebflow\Models\WebflowSite;

uses(RefreshDatabase::class)->group('event-tickets');

beforeEach(function () {
    $this->store = Store::factory()->create();
    $this->addon = Addon::factory()->for($this->store)->create([
        'type' => AddonType::EventTickets,
        'is_active' => true,
    ]);
    $this->site = WebflowSite::create([
        'store_id' => $this->store->id,
        'webflow_site_id' => 'site_'.uniqid(),
        'name' => 'Test Site',
        'is_active' => true,
    ]);
    $this->collection = WebflowCollection::create([
        'webflow_site_id' => $this->site->id,
        'webflow_collection_id' => 'col_'.uniqid(),
        'name' => 'Events',
        'is_active' => true,
    ]);
});

it('creates event tickets from webflow collection items', function () {
    WebflowItem::create([
        'webflow_collection_id' => $this->collection->id,
        'webflow_item_id' => 'item_1',
        'field_data' => [
            'name' => 'Concert',
            'slug' => 'concert',
        ],
    ]);

    $import = app(ImportEventTicketsFromWebflowCollection::class);
    $result = $import($this->store, $this->collection, false);

    expect($result['created'])->toBe(1);
    expect($result['updated'])->toBe(0);
    expect(EventTicket::where('store_id', $this->store->id)->count())->toBe(1);
    $ticket = EventTicket::first();
    expect($ticket->name)->toBe('Concert');
    expect($ticket->slug)->toBe('concert');
});

it('updates existing event ticket when webflow item already linked', function () {
    $item = WebflowItem::create([
        'webflow_collection_id' => $this->collection->id,
        'webflow_item_id' => 'item_1',
        'field_data' => ['name' => 'Old Name', 'slug' => 'old'],
    ]);
    EventTicket::create([
        'store_id' => $this->store->id,
        'webflow_item_id' => $item->id,
        'name' => 'Old Name',
        'slug' => 'old',
    ]);

    WebflowItem::where('id', $item->id)->update([
        'field_data' => ['name' => 'New Name', 'slug' => 'new'],
    ]);

    $import = app(ImportEventTicketsFromWebflowCollection::class);
    $result = $import($this->store, $this->collection, false);

    expect($result['created'])->toBe(0);
    expect($result['updated'])->toBe(1);
    $ticket = EventTicket::where('webflow_item_id', $item->id)->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->name)->toBe('New Name');
});

it('uses first active collection when collection argument is null', function () {
    WebflowItem::create([
        'webflow_collection_id' => $this->collection->id,
        'webflow_item_id' => 'item_1',
        'field_data' => ['name' => 'Event'],
    ]);

    $import = app(ImportEventTicketsFromWebflowCollection::class);
    $result = $import($this->store, null, false);

    expect($result['created'])->toBe(1);
});
