<?php

use App\Actions\ConnectedProducts\UpdateConnectedProductToStripe;
use App\Models\ConnectedProduct;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lanos\CashierConnect\Models\ConnectMapping;

uses(RefreshDatabase::class);

it('does not send unit_label when product type is not service', function () {
    config()->set('services.stripe.secret', 'sk_test_unit_label_filter');

    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_unit_label_filter',
    ]);

    $product = ConnectedProduct::withoutEvents(function () use ($store) {
        return ConnectedProduct::factory()->create([
            'stripe_account_id' => $store->stripe_account_id,
            'stripe_product_id' => 'prod_test_unit_label_filter',
            'type' => 'good',
            'unit_label' => 'kg',
        ]);
    });

    ConnectMapping::query()->create([
        'model' => Store::class,
        'model_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'type' => 'standard',
    ]);

    expect($store->fresh()->hasStripeAccount())->toBeTrue();

    $action = new class extends UpdateConnectedProductToStripe
    {
        public ?array $capturedPayload = null;

        protected function updateStripeProduct(ConnectedProduct $product, array $cleanUpdateData, string $secret): void
        {
            $this->capturedPayload = $cleanUpdateData;
        }
    };

    $action($product);

    $capturedPayload = $action->capturedPayload;

    expect($capturedPayload)->toBeArray()
        ->and($capturedPayload)->not->toHaveKey('unit_label');
});

it('sends unit_label when product type is service', function () {
    config()->set('services.stripe.secret', 'sk_test_unit_label_service');

    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_unit_label_service',
    ]);

    $product = ConnectedProduct::withoutEvents(function () use ($store) {
        return ConnectedProduct::factory()->create([
            'stripe_account_id' => $store->stripe_account_id,
            'stripe_product_id' => 'prod_test_unit_label_service',
            'type' => 'service',
            'unit_label' => 'hour',
        ]);
    });

    ConnectMapping::query()->create([
        'model' => Store::class,
        'model_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'type' => 'standard',
    ]);

    expect($store->fresh()->hasStripeAccount())->toBeTrue();

    $action = new class extends UpdateConnectedProductToStripe
    {
        public ?array $capturedPayload = null;

        protected function updateStripeProduct(ConnectedProduct $product, array $cleanUpdateData, string $secret): void
        {
            $this->capturedPayload = $cleanUpdateData;
        }
    };

    $action($product);

    $capturedPayload = $action->capturedPayload;

    expect($capturedPayload)->toBeArray()
        ->and($capturedPayload)->toHaveKey('unit_label')
        ->and($capturedPayload['unit_label'])->toBe('hour');
});
