<?php

use App\Models\PosDevice;
use App\Models\Store;
use App\Models\TerminalLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Tests\Support\StripePermissionDeniedTestHttpClient;

uses(RefreshDatabase::class);

afterEach(function (): void {
    ApiRequestor::setHttpClient(CurlClient::instance());
});

it('returns 404 when pos_device_id is not found for store', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create(['slug' => 'conn-token-store']);
    $user->stores()->attach($store);

    $otherStore = Store::factory()->create(['slug' => 'other-store']);
    $deviceInOtherStore = PosDevice::factory()->create(['store_id' => $otherStore->id]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/stores/conn-token-store/terminal/connection-token', [
        'pos_device_id' => $deviceInOtherStore->id,
    ]);

    $response->assertNotFound();
    $response->assertJson(['message' => 'POS device not found for this store.']);
});

it('returns 422 when stripe denies platform access to the connected account (connection token)', function (): void {
    config()->set('cashier.secret', 'sk_test_permission_denied');
    ApiRequestor::setHttpClient(new StripePermissionDeniedTestHttpClient);

    $user = User::factory()->create();
    $store = Store::factory()->create([
        'slug' => 'nw43-token-store',
        'stripe_account_id' => 'acct_nw43_example',
    ]);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $location = TerminalLocation::create([
        'store_id' => $store->id,
        'stripe_location_id' => 'tml_test_nw43',
        'display_name' => 'Counter',
        'line1' => 'Gate test',
        'city' => 'Oslo',
        'postal_code' => '0123',
        'country' => 'NO',
    ]);
    $store->update(['default_terminal_location_id' => $location->id]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/stores/nw43-token-store/terminal/connection-token', []);

    $response->assertUnprocessable();
    $response->assertJsonPath('success', false);
    expect($response->json('errors.stripe_account'))->toBeArray();
    expect($response->json('message'))->toContain('not accessible');
});

it('returns 422 when stripe denies platform access to the connected account (terminal payment intent)', function (): void {
    config()->set('cashier.secret', 'sk_test_permission_denied');
    ApiRequestor::setHttpClient(new StripePermissionDeniedTestHttpClient);

    $user = User::factory()->create();
    $store = Store::factory()->create([
        'slug' => 'nw43-pi-store',
        'stripe_account_id' => 'acct_nw43_pi_example',
    ]);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/stores/nw43-pi-store/terminal/payment-intents', [
        'amount' => 1000,
        'currency' => 'nok',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonPath('success', false);
    expect($response->json('errors.stripe_account'))->toBeArray();
    expect($response->json('message'))->toContain('not accessible');
});
