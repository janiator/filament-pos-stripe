<?php

use App\Models\ConnectedProduct;
use App\Models\PaymentMethod;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
use App\Models\VerifoneTerminal;
use App\Services\Verifone\VerifonePosCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('forbids listing verifone terminals for another store', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson("/api/verifone/stores/{$otherStore->slug}/terminals");

    $response->assertForbidden();
});

it('starts and polls a verifone payment with normalized status', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create([
        'slug' => 'vf-store',
        'verifone_api_base_url' => 'https://example.invalid',
        'verifone_user_uid' => 'user-uid',
        'verifone_api_key' => 'api-key',
    ]);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $terminal = VerifoneTerminal::factory()->create([
        'store_id' => $store->id,
        'terminal_identifier' => 'vm10000',
        'sale_id' => 'VisivoTest',
    ]);

    $mock = \Mockery::mock(VerifonePosCloudService::class);
    $mock->shouldReceive('processPayment')
        ->once()
        ->andReturn([
            'request_payload' => ['MessageHeader' => ['ServiceID' => '1234']],
            'response_payload' => ['ok' => true],
        ]);
    $mock->shouldReceive('transactionStatus')
        ->once()
        ->andReturn([
            'request_payload' => ['MessageHeader' => ['ServiceID' => '1234']],
            'response_payload' => ['raw' => true],
            'normalized_status' => [
                'status' => 'succeeded',
                'providerStatus' => ['result' => 'SUCCESS'],
                'providerPaymentReference' => '344433',
                'providerTransactionId' => '197501',
                'receipt' => [],
            ],
        ]);
    app()->instance(VerifonePosCloudService::class, $mock);

    Sanctum::actingAs($user, ['*']);

    $startResponse = $this->postJson("/api/verifone/stores/{$store->slug}/payments", [
        'terminal_id' => $terminal->id,
        'amount' => 1000,
        'currency' => 'NOK',
        'service_id' => '1234',
    ]);

    $startResponse->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('serviceId', '1234');

    $statusResponse = $this->postJson("/api/verifone/stores/{$store->slug}/payments/1234/status");
    $statusResponse->assertOk()
        ->assertJsonPath('status', 'succeeded')
        ->assertJsonPath('providerPaymentReference', '344433');

    $this->assertDatabaseHas('verifone_terminal_payments', [
        'store_id' => $store->id,
        'service_id' => '1234',
        'status' => 'succeeded',
        'provider_payment_reference' => '344433',
    ]);
});

it('starts a verifone payment using terminal_poiid', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create([
        'slug' => 'vf-store-poiid',
        'verifone_api_base_url' => 'https://example.invalid',
        'verifone_user_uid' => 'user-uid',
        'verifone_api_key' => 'api-key',
    ]);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $terminal = VerifoneTerminal::factory()->create([
        'store_id' => $store->id,
        'terminal_identifier' => 'vm10000',
        'sale_id' => 'VisivoTest',
    ]);

    $mock = \Mockery::mock(VerifonePosCloudService::class);
    $mock->shouldReceive('processPayment')
        ->once()
        ->andReturn([
            'request_payload' => ['MessageHeader' => ['ServiceID' => 'ABCD1234']],
            'response_payload' => ['ok' => true],
        ]);
    app()->instance(VerifonePosCloudService::class, $mock);

    Sanctum::actingAs($user, ['*']);

    $startResponse = $this->postJson("/api/verifone/stores/{$store->slug}/payments", [
        'terminal_poiid' => $terminal->terminal_identifier,
        'amount' => 1000,
        'currency' => 'NOK',
        'service_id' => 'ABCD1234',
    ]);

    $startResponse->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('serviceId', 'ABCD1234')
        ->assertJsonPath('terminalId', $terminal->terminal_identifier);
});

it('requires verifone reference metadata when payment provider is verifone', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_verifone_meta']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $posDevice = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $posDevice->id,
        'user_id' => $user->id,
        'status' => 'open',
    ]);
    PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'Verifone Terminal',
        'code' => 'verifone_terminal',
        'provider' => 'other',
        'provider_method' => 'terminal',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'minimum_amount_kroner' => null,
        'saf_t_payment_code' => '12002',
        'saf_t_event_code' => '13017',
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'verifone_terminal',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 1000,
                ],
            ],
            'total' => 1000,
            'currency' => 'nok',
        ],
        'metadata' => [
            'payment_provider' => 'verifone',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Verifone payment reference is required for Verifone payments');
});

it('uses encoded basic auth override when provided', function (): void {
    $store = Store::factory()->create([
        'verifone_api_base_url' => 'https://example.test',
        'verifone_user_uid' => null,
        'verifone_api_key' => null,
        'verifone_encoded_basic_auth' => 'dGVzdC11c2VyOnRlc3Qta2V5',
    ]);

    $terminal = VerifoneTerminal::factory()->create([
        'store_id' => $store->id,
        'terminal_identifier' => 'vm10000',
        'sale_id' => 'VisivoTest',
    ]);

    Http::fake(function (HttpRequest $request) {
        expect($request->hasHeader('Authorization'))->toBeTrue();
        expect($request->header('Authorization')[0])->toBe('Basic dGVzdC11c2VyOnRlc3Qta2V5');

        return Http::response(['ok' => true], 200);
    });

    $result = app(VerifonePosCloudService::class)->processPayment(
        $store,
        $terminal,
        200,
        'NOK',
        'ABCD1234'
    );

    expect($result['response_payload'])->toBe(['ok' => true]);
});

it('falls back to uid and api key after encoded basic auth is removed', function (): void {
    $store = Store::factory()->create([
        'verifone_api_base_url' => 'https://example.test',
        'verifone_user_uid' => 'fallback-user',
        'verifone_api_key' => 'fallback-key',
        'verifone_encoded_basic_auth' => 'dGVzdC11c2VyOnRlc3Qta2V5',
    ]);

    $store->update([
        'verifone_encoded_basic_auth' => null,
    ]);

    $terminal = VerifoneTerminal::factory()->create([
        'store_id' => $store->id,
        'terminal_identifier' => 'vm10000',
        'sale_id' => 'VisivoTest',
    ]);

    Http::fake(function (HttpRequest $request) {
        expect($request->hasHeader('Authorization'))->toBeTrue();
        expect($request->header('Authorization')[0])->toBe('Basic ZmFsbGJhY2stdXNlcjpmYWxsYmFjay1rZXk=');

        return Http::response(['ok' => true], 200);
    });

    $result = app(VerifonePosCloudService::class)->processPayment(
        $store->fresh(),
        $terminal,
        200,
        'NOK',
        'ABCD1234'
    );

    expect($result['response_payload'])->toBe(['ok' => true]);
});

it('allows verifone as a payment method provider', function (): void {
    $store = Store::factory()->create();

    $paymentMethod = PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'Verifone Terminal',
        'code' => 'verifone_terminal',
        'provider' => 'verifone',
        'provider_method' => 'terminal',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'minimum_amount_kroner' => null,
        'saf_t_payment_code' => '12002',
        'saf_t_event_code' => '13017',
    ]);

    expect($paymentMethod->provider)->toBe('verifone');
    $this->assertDatabaseHas('payment_methods', [
        'id' => $paymentMethod->id,
        'provider' => 'verifone',
    ]);
});

it('normalizes completed transactionstatus payload to succeeded', function (): void {
    $service = app(VerifonePosCloudService::class);

    $normalized = $service->normalizeStatus([
        'TransactionStatusResponse' => [
            'Response' => [
                'Result' => 'SUCCESS',
                'ErrorCondition' => null,
                'AdditionalResponse' => null,
            ],
            'RepeatedMessageResponse' => [
                'RepeatedResponseMessageBody' => [
                    'PaymentResponse' => [
                        'Response' => [
                            'Result' => 'SUCCESS',
                            'ErrorCondition' => null,
                            'AdditionalResponse' => null,
                        ],
                        'SaleData' => [
                            'SaleToPOIData' => json_encode([
                                'i' => 'Z2IJVIFD',
                                'rc' => 'Success',
                                'td' => '80906266',
                            ]),
                        ],
                        'POIData' => [
                            'POITransactionID' => [
                                'TransactionID' => '000006',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect($normalized['status'])->toBe('succeeded');
    expect($normalized['providerPaymentReference'])->toBe('Z2IJVIFD');
    expect($normalized['providerTransactionId'])->toBe('80906266');
});
