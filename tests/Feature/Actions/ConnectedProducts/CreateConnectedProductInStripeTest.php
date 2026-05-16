<?php

use App\Actions\ConnectedProducts\CreateConnectedProductInStripe;
use App\Models\ConnectedProduct;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lanos\CashierConnect\Models\ConnectMapping;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

afterEach(function () {
    Mockery::close();
});

it('omits unit_label from Stripe product create payload when type is good', function () {
    config()->set('cashier.secret', null);
    config()->set('services.stripe.secret', 'sk_test_create_unit_label_good');

    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_create_unit_label_good',
    ]);

    ConnectMapping::query()->create([
        'model' => Store::class,
        'model_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'type' => 'standard',
    ]);

    $product = ConnectedProduct::withoutEvents(fn () => ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'type' => 'good',
        'unit_label' => 'kg',
        'name' => 'Weighted Good',
    ]));

    expect($store->fresh()->hasStripeAccount())->toBeTrue();

    $mockStripe = Mockery::mock('alias:'.StripeClient::class);
    $mockProducts = Mockery::mock();
    $mockStripe->shouldReceive('__construct')->once()->andReturnSelf();
    $mockStripe->products = $mockProducts;

    $mockProducts->shouldReceive('create')
        ->once()
        ->with(
            Mockery::on(fn (array $data): bool => ($data['type'] ?? null) === 'good'
                && ! array_key_exists('unit_label', $data)
                && ($data['name'] ?? null) === 'Weighted Good'
            ),
            ['stripe_account' => $store->stripe_account_id]
        )
        ->andReturn((object) ['id' => 'prod_create_good_1']);

    $action = new CreateConnectedProductInStripe;
    $result = $action($product);

    expect($result)->toBe('prod_create_good_1');
});

it('includes unit_label on Stripe product create when type is service', function () {
    config()->set('cashier.secret', null);
    config()->set('services.stripe.secret', 'sk_test_create_unit_label_service');

    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_create_unit_label_service',
    ]);

    ConnectMapping::query()->create([
        'model' => Store::class,
        'model_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'type' => 'standard',
    ]);

    $product = ConnectedProduct::withoutEvents(fn () => ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'type' => 'service',
        'unit_label' => 'hour',
        'name' => 'Consulting',
    ]));

    expect($store->fresh()->hasStripeAccount())->toBeTrue();

    $mockStripe = Mockery::mock('alias:'.StripeClient::class);
    $mockProducts = Mockery::mock();
    $mockStripe->shouldReceive('__construct')->once()->andReturnSelf();
    $mockStripe->products = $mockProducts;

    $mockProducts->shouldReceive('create')
        ->once()
        ->with(
            Mockery::on(fn (array $data): bool => ($data['type'] ?? null) === 'service'
                && ($data['unit_label'] ?? null) === 'hour'
                && ($data['name'] ?? null) === 'Consulting'
            ),
            ['stripe_account' => $store->stripe_account_id]
        )
        ->andReturn((object) ['id' => 'prod_create_service_1']);

    $action = new CreateConnectedProductInStripe;
    $result = $action($product);

    expect($result)->toBe('prod_create_service_1');
});
