<?php

use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('get current store returns settings with show_article_group_codes_in_pos', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    Setting::getForStore($store->id);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/stores/current');

    $response->assertOk();
    $response->assertJsonPath('store.settings.show_article_group_codes_in_pos', true);
});

test('get current store respects show_article_group_codes_in_pos when false', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $settings = Setting::getForStore($store->id);
    $settings->update(['show_article_group_codes_in_pos' => false]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/stores/current');

    $response->assertOk();
    $response->assertJsonPath('store.settings.show_article_group_codes_in_pos', false);
});
