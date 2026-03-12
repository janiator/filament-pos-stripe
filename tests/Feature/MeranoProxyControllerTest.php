<?php

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_'.str_replace('-', '', fake()->uuid()),
    ]);
    $this->user = User::factory()->create();
    $this->user->stores()->attach($this->store);
    $this->user->setCurrentStore($this->store);
    Sanctum::actingAs($this->user, ['*']);
});

test('pos device response includes booking action only when addon is active and device is enabled', function () {
    Addon::factory()->for($this->store)->create([
        'type' => AddonType::MeranoBooking,
        'is_active' => true,
    ]);

    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => false,
        'booking_enabled' => true,
    ]);

    $response = $this->getJson("/api/pos-devices/{$device->id}");

    $response->assertOk();
    $response->assertJsonPath('device.booking_enabled', true);
    $response->assertJsonPath('device.available_actions.0', 'booking');
    $response->assertJsonCount(1, 'device.available_actions');
});

test('merano proxy returns service unavailable when addon is inactive', function () {
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'booking_enabled' => true,
    ]);

    $response = $this->postJson('/api/merano/v1/bookings', [
        'pos_device_id' => $device->id,
        'event_id' => 10,
        'seats' => ['A-1'],
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'payment_type' => 'pos',
    ]);

    $response->assertStatus(503);
    $response->assertJsonPath('message', 'Merano booking is not enabled for this store.');
});

test('merano proxy returns service unavailable when addon is active but merano is not configured', function () {
    Addon::factory()->for($this->store)->create([
        'type' => AddonType::MeranoBooking,
        'is_active' => true,
    ]);

    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'booking_enabled' => true,
    ]);

    $response = $this->postJson('/api/merano/v1/bookings', [
        'pos_device_id' => $device->id,
        'event_id' => 10,
        'seats' => ['A-1'],
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'payment_type' => 'pos',
    ]);

    $response->assertStatus(503);
    $response->assertJsonPath('message', 'Merano is not configured for this store.');
});

test('merano proxy rejects booking for a device with booking disabled', function () {
    Addon::factory()->for($this->store)->create([
        'type' => AddonType::MeranoBooking,
        'is_active' => true,
    ]);

    $this->store->update([
        'merano_base_url' => 'https://merano.example.com',
        'merano_pos_api_token' => 'merano-token',
    ]);

    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'booking_enabled' => false,
    ]);

    $response = $this->postJson('/api/merano/v1/bookings', [
        'pos_device_id' => $device->id,
        'event_id' => 10,
        'seats' => ['A-1'],
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'payment_type' => 'pos',
    ]);

    $response->assertForbidden();
    $response->assertJsonPath('message', 'Booking is not enabled for this device.');
});

test('merano proxy forwards create booking request when configured', function () {
    Addon::factory()->for($this->store)->create([
        'type' => AddonType::MeranoBooking,
        'is_active' => true,
    ]);

    $this->store->update([
        'merano_base_url' => 'https://merano.example.com',
        'merano_pos_api_token' => 'merano-token',
    ]);

    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'booking_enabled' => true,
    ]);

    Http::fake([
        'https://merano.example.com/api/pos/v1/bookings' => Http::response([
            'booking_id' => 123,
            'booking_number' => 'BK-123',
            'status' => 'pending',
        ], 201),
    ]);

    $response = $this->postJson('/api/merano/v1/bookings', [
        'pos_device_id' => $device->id,
        'event_id' => 10,
        'seats' => ['A-1', 'A-2'],
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '12345678',
        'payment_type' => 'pos',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('booking_id', 123);
    $response->assertJsonPath('booking_number', 'BK-123');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://merano.example.com/api/pos/v1/bookings'
            && ($request->header('X-POS-API-Token')[0] ?? null) === 'merano-token'
            && str_contains($request->header('Authorization')[0] ?? '', 'Bearer merano-token')
            && $request->data()['event_id'] === 10
            && $request->data()['seats'] === ['A-1', 'A-2']
            && ! array_key_exists('pos_device_id', $request->data());
    });
});

test('merano pos api token is stored encrypted on stores', function () {
    $this->store->update([
        'merano_pos_api_token' => 'super-secret-token',
    ]);

    $freshStore = $this->store->fresh();

    expect($freshStore?->merano_pos_api_token)->toBe('super-secret-token');
    expect($freshStore?->getRawOriginal('merano_pos_api_token'))->not->toBe('super-secret-token');
});

test('merano proxy can derive the booking device from the pos session id', function () {
    Addon::factory()->for($this->store)->create([
        'type' => AddonType::MeranoBooking,
        'is_active' => true,
    ]);

    $this->store->update([
        'merano_base_url' => 'https://merano.example.com',
        'merano_pos_api_token' => 'merano-token',
    ]);

    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'booking_enabled' => true,
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);

    Http::fake([
        'https://merano.example.com/api/pos/v1/bookings/123/confirm-pos-payment' => Http::response([
            'booking_id' => 123,
            'status' => 'succeeded',
        ], 200),
    ]);

    $response = $this->postJson('/api/merano/v1/bookings/123/confirm-pos-payment', [
        'pos_session_id' => $session->id,
        'amount_paid_ore' => 5000,
        'pos_charge_id' => 'ch_123',
        'currency' => 'NOK',
    ]);

    $response->assertOk();
    $response->assertJsonPath('booking_id', 123);
});

test('confirm-pos-payment accepts amount_paid_ore 0 for freeticket orders', function () {
    Addon::factory()->for($this->store)->create([
        'type' => AddonType::MeranoBooking,
        'is_active' => true,
    ]);

    $this->store->update([
        'merano_base_url' => 'https://merano.example.com',
        'merano_pos_api_token' => 'merano-token',
    ]);

    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'booking_enabled' => true,
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);

    Http::fake([
        'https://merano.example.com/api/pos/v1/bookings/123/confirm-pos-payment' => Http::response([
            'booking_id' => 123,
            'status' => 'succeeded',
        ], 200),
    ]);

    $response = $this->postJson('/api/merano/v1/bookings/123/confirm-pos-payment', [
        'pos_session_id' => $session->id,
        'amount_paid_ore' => 0,
        'pos_charge_id' => 'ch_freeticket',
        'currency' => 'NOK',
    ]);

    $response->assertOk();
    $response->assertJsonPath('booking_id', 123);
});
