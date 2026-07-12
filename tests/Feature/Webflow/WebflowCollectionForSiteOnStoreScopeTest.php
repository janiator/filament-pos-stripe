<?php

use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Models\WebflowSite;

uses()->group('webflow');

it('does not define WebflowSite::addon and scopes collections via site store_id in SQL', function (): void {
    expect(method_exists(WebflowSite::class, 'addon'))->toBeFalse();

    $sql = WebflowCollection::query()->forSiteOnStore(42)->toSql();

    expect($sql)
        ->toContain('store_id')
        ->toContain('webflow_sites');
});

it('does not add tenant constraint when forSiteOnStore receives null or empty string', function (): void {
    expect(WebflowCollection::query()->forSiteOnStore(null)->toSql())
        ->toBe('select * from "webflow_collections"');

    expect(WebflowCollection::query()->forSiteOnStore('')->toSql())
        ->toBe('select * from "webflow_collections"');
});
