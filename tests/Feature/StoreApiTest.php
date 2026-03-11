<?php

use App\Models\ArticleGroupCode;
use App\Models\ConnectedProduct;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('get current store returns visible_article_group_codes', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_123']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    ArticleGroupCode::create([
        'stripe_account_id' => 'acct_test_123',
        'code' => '04003',
        'name' => 'Varesalg',
        'active' => true,
        'show_in_pos' => true,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/stores/current');

    $response->assertOk();
    $response->assertJsonPath('store.visible_article_group_codes.0.code', '04003');
    $response->assertJsonPath('store.visible_article_group_codes.0.name', 'Varesalg');
});

test('get current store excludes article group codes with show_in_pos false', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_456']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    ArticleGroupCode::create([
        'stripe_account_id' => 'acct_test_456',
        'code' => '04004',
        'name' => 'Hidden in POS',
        'active' => true,
        'show_in_pos' => false,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/stores/current');

    $response->assertOk();
    $codes = $response->json('store.visible_article_group_codes');
    $codes = collect($codes)->pluck('code')->all();
    expect($codes)->not->toContain('04004');
});

test('get current store returns customers_enabled from settings', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    Setting::create([
        'store_id' => $store->id,
        'customers_enabled' => true,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/stores/current');

    $response->assertOk();
    $response->assertJsonPath('store.customers_enabled', true);
});

test('get current store returns customers_enabled false when disabled in settings', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    Setting::create([
        'store_id' => $store->id,
        'customers_enabled' => false,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/stores/current');

    $response->assertOk();
    $response->assertJsonPath('store.customers_enabled', false);
});

test('list stores includes customers_enabled for each store', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);

    Setting::create([
        'store_id' => $store->id,
        'customers_enabled' => false,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/stores');

    $response->assertOk();
    $response->assertJsonPath('stores.0.customers_enabled', false);
});

test('get merano ticket product returns 404 when not configured', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_merano']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/stores/current/merano-ticket-product');

    $response->assertNotFound();
    $response->assertJsonPath('message', 'Merano ticket product not configured for this store');
});

test('get merano ticket product returns product when configured', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_merano']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'Merano Ticket',
    ]);
    $store->update(['merano_ticket_connected_product_id' => $product->id]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/stores/current/merano-ticket-product');

    $response->assertOk();
    $response->assertJsonPath('product.id', $product->id);
    $response->assertJsonPath('product.name', 'Merano Ticket');
});
