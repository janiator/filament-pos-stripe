<?php

use App\Actions\ConnectedPrices\SyncProductPrice;
use App\Models\ConnectedPrice;
use App\Models\ConnectedProduct;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    Log::spy();
});

it('reads the current attribute value, not getRawOriginal, for price resolution', function () {
    $store = Store::factory()->create();

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'price' => '50.00',
        'currency' => 'nok',
    ]);

    // Existing price matching 50.00 (the old price)
    $oldPrice = ConnectedPrice::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_product_id' => $product->stripe_product_id,
        'unit_amount' => 5000,
        'currency' => 'nok',
        'active' => true,
    ]);

    // Existing price matching 75.00 (the new price)
    $newPrice = ConnectedPrice::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_product_id' => $product->stripe_product_id,
        'unit_amount' => 7500,
        'currency' => 'nok',
        'active' => true,
    ]);

    $product->default_price = $oldPrice->stripe_price_id;
    $product->saveQuietly();

    // Change price in attributes (simulating what happens before/during save)
    $product->price = '75.00';

    $syncAction = new SyncProductPrice;
    $syncAction($product);

    // The action should select the price matching 75.00, not 50.00
    expect($product->fresh()->default_price)->toBe($newPrice->stripe_price_id);
});

it('finds existing price and skips creation when amount already matches', function () {
    $store = Store::factory()->create();

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'price' => '50.00',
        'currency' => 'nok',
    ]);

    $existingPrice = ConnectedPrice::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_product_id' => $product->stripe_product_id,
        'unit_amount' => 5000,
        'currency' => 'nok',
        'active' => true,
    ]);

    $product->default_price = $existingPrice->stripe_price_id;
    $product->saveQuietly();

    $syncAction = new SyncProductPrice;
    $syncAction($product);

    expect($product->fresh()->default_price)->toBe($existingPrice->stripe_price_id);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $msg) => str_contains($msg, 'Price already exists'));
});

it('skips sync for products without stripe_product_id', function () {
    $store = Store::factory()->create();

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_product_id' => null,
        'price' => '50.00',
        'currency' => 'nok',
    ]);

    $syncAction = new SyncProductPrice;
    $syncAction($product);

    expect(ConnectedPrice::where('stripe_product_id', null)->count())->toBe(0);
});

