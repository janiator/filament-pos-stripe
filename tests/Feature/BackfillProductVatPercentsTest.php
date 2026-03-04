<?php

use App\Models\ArticleGroupCode;
use App\Models\ConnectedProduct;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('backfills vat_percent from tax code default when product has null vat_percent', function () {
    $store = Store::factory()->create([
        'name' => 'Test Store Backfill',
        'slug' => 'test-backfill-vat',
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'vat_percent' => null,
        'article_group_code' => null,
        'tax_code' => null,
        'name' => 'Test Product',
    ]);

    expect($product->vat_percent)->toBeNull();

    $this->artisan('products:backfill-vat-percents', ['store' => 'test-backfill-vat'])
        ->assertSuccessful();

    $product->refresh();
    expect($product->vat_percent)->not->toBeNull()
        ->and((float) $product->vat_percent)->toBe(25.0);
});

it('dry run does not update products', function () {
    $store = Store::factory()->create([
        'name' => 'Test Store Dry Run',
        'slug' => 'test-backfill-dry-run',
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'vat_percent' => null,
        'article_group_code' => null,
        'tax_code' => null,
    ]);

    $this->artisan('products:backfill-vat-percents', [
        'store' => 'test-backfill-dry-run',
        '--dry-run' => true,
    ])->assertSuccessful();

    $product->refresh();
    expect($product->vat_percent)->toBeNull();
});

it('backfills vat_percent from article group code when product has article_group_code', function () {
    $store = Store::factory()->create([
        'name' => 'Test Store Article Group',
        'slug' => 'test-backfill-ag',
    ]);

    ArticleGroupCode::create([
        'code' => 'TESTAG',
        'name' => 'Test group',
        'stripe_account_id' => $store->stripe_account_id,
        'default_vat_percent' => 0.15,
        'active' => true,
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'vat_percent' => null,
        'article_group_code' => 'TESTAG',
        'tax_code' => null,
        'name' => 'Product with article group',
    ]);

    expect($product->vat_percent)->toBeNull();

    $this->artisan('products:backfill-vat-percents', ['store' => 'test-backfill-ag'])
        ->assertSuccessful();

    $product->refresh();
    expect((float) $product->vat_percent)->toBe(15.0);
});

it('skips products that already have vat_percent set', function () {
    $store = Store::factory()->create([
        'name' => 'Test Store Skip',
        'slug' => 'test-backfill-skip',
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'vat_percent' => 15.00,
        'article_group_code' => null,
        'tax_code' => null,
    ]);

    $this->artisan('products:backfill-vat-percents', ['store' => 'test-backfill-skip'])
        ->assertSuccessful();

    $product->refresh();
    expect((float) $product->vat_percent)->toBe(15.0);
});
