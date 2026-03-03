<?php

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\Store;
use Illuminate\Support\Facades\Artisan;
use Positiv\FilamentWebflow\Models\WebflowSite;

uses()->group('addons');

beforeEach(function () {
    $this->store = Store::factory()->create();
});

it('creates addon for store and types with webflow are correct', function () {
    $addon = Addon::factory()->for($this->store)->create([
        'type' => AddonType::WebflowCms,
        'is_active' => true,
    ]);

    expect($addon->store_id)->toBe($this->store->id);
    expect($addon->type)->toBe(AddonType::WebflowCms);
    expect($addon->is_active)->toBeTrue();
    expect(AddonType::typesWithWebflow())->toContain(AddonType::WebflowCms->value, AddonType::EventTickets->value);
});

it('store has addons and webflow sites', function () {
    $addon = Addon::factory()->for($this->store)->create(['type' => AddonType::WebflowCms]);

    expect($this->store->addons)->toHaveCount(1);
    expect($this->store->addons->first()->id)->toBe($addon->id);

    $site = WebflowSite::create([
        'store_id' => $this->store->id,
        'webflow_site_id' => 'webflow_'.uniqid(),
        'name' => 'Test Site',
        'is_active' => true,
    ]);

    expect($this->store->webflowSites)->toHaveCount(1);
    expect($this->store->webflowSites->first()->id)->toBe($site->id);
});

it('event tickets addon type is in types with webflow', function () {
    $addon = Addon::factory()->for($this->store)->eventTickets()->create();

    expect($addon->type)->toBe(AddonType::EventTickets);
    expect(AddonType::typesWithWebflow())->toContain($addon->type->value);
});

it('import event tickets command completes with zero created and updated when no webflow collection for store', function () {
    Addon::factory()->for($this->store)->eventTickets()->create();

    $exitCode = Artisan::call('event-tickets:import-from-webflow', ['store' => $this->store->slug]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Created: 0, Updated: 0');
});
