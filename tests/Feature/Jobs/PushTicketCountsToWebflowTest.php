<?php

use App\Jobs\PushTicketCountsToWebflow;
use App\Models\EventTicket;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Models\WebflowItem;
use Positiv\FilamentWebflow\Models\WebflowSite;

uses(RefreshDatabase::class);

it('pushes ticket counts to Webflow using collection field_mapping', function () {
    $store = Store::factory()->create();
    $site = WebflowSite::create([
        'store_id' => $store->id,
        'webflow_site_id' => 'site_'.uniqid(),
        'name' => 'Test',
        'api_token' => 'token_'.uniqid(),
        'is_active' => true,
    ]);
    $collectionId = 'col_'.uniqid();
    $collection = WebflowCollection::create([
        'webflow_site_id' => $site->id,
        'webflow_collection_id' => $collectionId,
        'name' => 'Events',
        'is_active' => true,
        'field_mapping' => [
            'ticket_1_sold' => 'custom-sold-1',
            'is_sold_out' => 'sold-out-flag',
        ],
    ]);
    $itemId = 'item_'.uniqid();
    $item = WebflowItem::create([
        'webflow_collection_id' => $collection->id,
        'webflow_item_id' => $itemId,
        'field_data' => ['name' => 'Event'],
        'is_published' => false,
    ]);
    $eventTicket = EventTicket::create([
        'store_id' => $store->id,
        'webflow_item_id' => $item->id,
        'name' => 'Event',
        'ticket_1_sold' => 7,
        'ticket_2_sold' => 0,
        'is_sold_out' => true,
    ]);

    $patchPayload = null;
    Http::fake([
        '*.webflow.com/*' => function ($request) use (&$patchPayload) {
            if (str_starts_with($request->url(), 'https://') && $request->method() === 'PATCH') {
                $patchPayload = json_decode($request->body(), true);
            }

            return Http::response([]);
        },
    ]);

    $job = new PushTicketCountsToWebflow($eventTicket);
    $job->handle();

    expect($patchPayload)->not->toBeNull();
    expect($patchPayload)->toHaveKey('fieldData');
    $fieldData = $patchPayload['fieldData'];
    expect($fieldData)->toHaveKey('custom-sold-1', 7);
    expect($fieldData['custom-sold-1'])->toBe(7);
    expect($fieldData)->toHaveKey('sold-out-flag');
    expect($fieldData['sold-out-flag'])->toBe(true);
});
