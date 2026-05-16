<?php

use App\Actions\ConnectedProducts\CreateConnectedProductInStripe;
use App\Models\ConnectedProduct;

it('buildStripeProductCreatePayload omits unit_label for good type products', function (): void {
    $payloadTester = new class extends CreateConnectedProductInStripe
    {
        public function testPayload(ConnectedProduct $product): array
        {
            return $this->buildStripeProductCreatePayload($product);
        }
    };

    $product = new ConnectedProduct([
        'name' => 'Weight bag',
        'stripe_account_id' => 'acct_test_good',
        'type' => 'good',
        'unit_label' => 'stk',
    ]);

    $payload = $payloadTester->testPayload($product);

    expect($payload['type'])->toBe('good')
        ->and($payload)->not->toHaveKey('unit_label');
});

it('buildStripeProductCreatePayload includes unit_label for service type products', function (): void {
    $payloadTester = new class extends CreateConnectedProductInStripe
    {
        public function testPayload(ConnectedProduct $product): array
        {
            return $this->buildStripeProductCreatePayload($product);
        }
    };

    $product = new ConnectedProduct([
        'name' => 'Hourly tune-up',
        'stripe_account_id' => 'acct_test_service',
        'type' => 'service',
        'unit_label' => 'hour',
    ]);

    $payload = $payloadTester->testPayload($product);

    expect($payload['type'])->toBe('service')
        ->and($payload)->toHaveKey('unit_label')
        ->and($payload['unit_label'])->toBe('hour');
});

it('buildStripeProductCreatePayload includes unit_label when type defaults to service', function (): void {
    $payloadTester = new class extends CreateConnectedProductInStripe
    {
        public function testPayload(ConnectedProduct $product): array
        {
            return $this->buildStripeProductCreatePayload($product);
        }
    };

    $product = new ConnectedProduct([
        'name' => 'Default type product',
        'stripe_account_id' => 'acct_test_default_service',
        'unit_label' => 'seat',
    ]);

    $payload = $payloadTester->testPayload($product);

    expect($payload['type'])->toBe('service')
        ->and($payload['unit_label'])->toBe('seat');
});
