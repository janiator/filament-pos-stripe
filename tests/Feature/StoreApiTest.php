<?php

use App\Models\ArticleGroupCode;
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
