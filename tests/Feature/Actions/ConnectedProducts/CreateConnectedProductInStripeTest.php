<?php

use App\Actions\ConnectedProducts\CreateConnectedProductInStripe;
use App\Models\ConnectedProduct;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lanos\CashierConnect\Models\ConnectMapping;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

afterEach(function () {
    \Mockery::close();
});

it('omits unit_label for non-service products and includes it for service products', function () {
    config()->set('services.stripe.secret', 'sk_test_create_unit_label');

    $mockStripe = \Mockery::mock('alias:'.StripeClient::class);
    $mockProducts = \Mockery::mock();
    $mockStripe->shouldReceive('__construct')->andReturnSelf();
    $mockStripe->products = $mockProducts;

    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_create_unit_label',
    ]);

    ConnectMapping::query()->create([
        'model' => Store::class,
        'model_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'type' => 'standard',
    ]);

    expect($store->fresh()->hasStripeAccount())->toBeTrue();

    $captures = [];

    $mockProducts->shouldReceive('create')
        ->twice()
        ->withArgs(function (array $data, array $opts) use (&$captures, $store) {
            $captures[] = $data;

            return ($opts['stripe_account'] ?? null) === $store->stripe_account_id;
        })
        ->andReturn((object) ['id' => 'prod_created_test']);

    $goodProduct = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'type' => 'good',
        'unit_label' => 'kg',
    ]);

    $serviceProduct = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'type' => 'service',
        'unit_label' => 'hour',
    ]);

    $action = new CreateConnectedProductInStripe;

    $action($goodProduct);
    $action($serviceProduct);

    expect($captures)->toHaveCount(2)
        ->and($captures[0])->not->toHaveKey('unit_label')
        ->and($captures[0]['type'])->toBe('good')
        ->and($captures[1])->toHaveKey('unit_label')
        ->and($captures[1]['unit_label'])->toBe('hour')
        ->and($captures[1]['type'])->toBe('service');
});
