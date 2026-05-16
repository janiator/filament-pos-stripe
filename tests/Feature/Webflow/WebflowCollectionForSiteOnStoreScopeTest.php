<?php

declare(strict_types=1);

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Models\WebflowSite;

uses(RefreshDatabase::class)->group('webflow');

it('scopes webflow collections via webflow site store_id without an addon relation on WebflowSite', function (): void {
    expect(method_exists(WebflowSite::class, 'addon'))->toBeFalse();

    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();

    $siteA = WebflowSite::create([
        'store_id' => $storeA->id,
        'webflow_site_id' => 'webflow_a_'.uniqid(),
        'name' => 'Site A',
        'is_active' => true,
    ]);
    $siteB = WebflowSite::create([
        'store_id' => $storeB->id,
        'webflow_site_id' => 'webflow_b_'.uniqid(),
        'name' => 'Site B',
        'is_active' => true,
    ]);

    $collectionForA = WebflowCollection::create([
        'webflow_site_id' => $siteA->id,
        'webflow_collection_id' => 'col_a_'.uniqid(),
        'name' => 'Collection A',
        'is_active' => true,
    ]);
    WebflowCollection::create([
        'webflow_site_id' => $siteB->id,
        'webflow_collection_id' => 'col_b_'.uniqid(),
        'name' => 'Collection B',
        'is_active' => true,
    ]);

    $scoped = WebflowCollection::query()->forSiteOnStore($storeA->id)->pluck('id')->all();

    expect($scoped)->toContain($collectionForA->id)->and($scoped)->toHaveCount(1);
});

it('does not filter by store when the store key for scopeForSiteOnStore is null', function (): void {
    $store = Store::factory()->create();
    $site = WebflowSite::create([
        'store_id' => $store->id,
        'webflow_site_id' => 'webflow_'.uniqid(),
        'name' => 'Only site',
        'is_active' => true,
    ]);
    WebflowCollection::create([
        'webflow_site_id' => $site->id,
        'webflow_collection_id' => 'col_'.uniqid(),
        'name' => 'Collection',
        'is_active' => true,
    ]);

    expect(WebflowCollection::query()->forSiteOnStore(null)->count())->toBe(1);
});
