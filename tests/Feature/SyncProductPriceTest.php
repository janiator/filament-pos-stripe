<?php

use App\Actions\ConnectedPrices\CreateConnectedPriceInStripe;
use App\Actions\ConnectedPrices\SyncProductPrice;
use App\Models\ConnectedPrice;
use App\Models\ConnectedProduct;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Lanos\CashierConnect\Models\ConnectMapping;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

beforeEach(function () {
    Log::spy();
});

afterEach(function () {
    Mockery::close();
});

it('calls Stripe products.update to set default before prices.delete when creating a replacement price', function () {
    config(['cashier.secret' => 'sk_test_fixture']);

    $this->mock(CreateConnectedPriceInStripe::class)
        ->shouldReceive('__invoke')
        ->once()
        ->andReturn('price_new_fixture');

    $store = Store::factory()->create();
    ConnectMapping::query()->create([
        'model' => Store::class,
        'model_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'type' => 'standard',
    ]);
    $accountId = $store->stripe_account_id;

    $product = ConnectedProduct::withoutEvents(fn (): ConnectedProduct => ConnectedProduct::factory()->create([
        'stripe_account_id' => $accountId,
        'stripe_product_id' => 'prod_sync_api_order_test',
        'price' => '75.00',
        'currency' => 'nok',
    ]));

    $previousDefaultPriceId = 'price_previous_default_test';

    ConnectedPrice::create([
        'stripe_price_id' => $previousDefaultPriceId,
        'stripe_account_id' => $accountId,
        'stripe_product_id' => $product->stripe_product_id,
        'unit_amount' => 5000,
        'currency' => 'nok',
        'type' => 'one_time',
        'active' => true,
        'billing_scheme' => 'per_unit',
        'metadata' => [],
        'nickname' => null,
        'recurring_interval' => null,
        'recurring_interval_count' => null,
        'recurring_usage_type' => null,
        'recurring_aggregate_usage' => null,
        'tiers_mode' => null,
    ]);

    $product->default_price = $previousDefaultPriceId;
    $product->saveQuietly();

    $stripeCallOrder = [];
    $newPriceId = 'price_new_fixture';

    $mockPrices = Mockery::mock();
    $mockPrices->shouldReceive('delete')->once()->andReturnUsing(function (
        string $priceId,
        array $_params,
        array $opts,
    ) use (&$stripeCallOrder, $previousDefaultPriceId, $accountId): object {
        $stripeCallOrder[] = 'prices.delete';
        expect($priceId)->toBe($previousDefaultPriceId)
            ->and($opts['stripe_account'] ?? null)->toBe($accountId);

        return (object) ['id' => $priceId, 'deleted' => true];
    });

    $mockProducts = Mockery::mock();
    $mockProducts->shouldReceive('update')->once()->andReturnUsing(function (
        string $stripeProductId,
        array $payload,
        array $opts,
    ) use (&$stripeCallOrder, $product, $newPriceId, $accountId): object {
        $stripeCallOrder[] = 'products.update';

        expect($stripeProductId)->toBe($product->stripe_product_id)
            ->and($payload['default_price'] ?? null)->toBe($newPriceId)
            ->and($opts['stripe_account'] ?? null)->toBe($accountId);

        return (object) ['id' => $stripeProductId];
    });

    $stripeClient = Mockery::mock(StripeClient::class);
    $stripeClient->prices = $mockPrices;
    $stripeClient->products = $mockProducts;

    $sync = new class($stripeClient) extends SyncProductPrice
    {
        public function __construct(private readonly StripeClient $stripeClientInstance) {}

        protected function makeStripeClient(string $secret): StripeClient
        {
            return $this->stripeClientInstance;
        }

        protected function priceHasBeenUsed(ConnectedPrice $price): bool
        {
            return false;
        }
    };

    $sync->__invoke($product);

    expect($stripeCallOrder)->toBe([
        'products.update',
        'prices.delete',
    ]);
    expect($product->fresh()->default_price)->toBe($newPriceId)
        ->and(ConnectedPrice::query()->where('stripe_price_id', $previousDefaultPriceId)->exists())->toBeFalse();
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
