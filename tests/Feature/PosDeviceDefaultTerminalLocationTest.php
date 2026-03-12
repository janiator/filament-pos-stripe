<?php

use App\Models\PosDevice;
use App\Models\Store;
use App\Models\TerminalLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('assigns store default terminal location to newly registered POS device', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $location = TerminalLocation::create([
        'store_id' => $store->id,
        'display_name' => 'Default counter',
        'line1' => 'Street 1',
        'city' => 'Oslo',
        'postal_code' => '0123',
        'country' => 'NO',
    ]);
    $store->update(['default_terminal_location_id' => $location->id]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/pos-devices', [
        'device_identifier' => 'unique-device-id-'.uniqid(),
        'device_name' => 'New iPad',
        'platform' => 'ios',
    ]);

    $response->assertStatus(201);
    $deviceId = $response->json('device.id');
    expect($deviceId)->not->toBeNull();

    $device = PosDevice::with('terminalLocations')->find($deviceId);
    expect($device->terminalLocations)->toHaveCount(1);
    expect($device->terminalLocations->first()->id)->toBe($location->id);
    expect($response->json('device.terminal_location_id'))->toBe($location->id);
});

it('registers POS device without terminal location when store has no default', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/pos-devices', [
        'device_identifier' => 'unique-device-id-'.uniqid(),
        'device_name' => 'New iPad',
        'platform' => 'ios',
    ]);

    $response->assertStatus(201);
    $deviceId = $response->json('device.id');
    $device = PosDevice::with('terminalLocations')->find($deviceId);
    expect($device->terminalLocations)->toHaveCount(0);
    expect($response->json('device.terminal_location_id'))->toBeNull();
});

it('returns auto_print_receipt on device and defaults to true when registering', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/pos-devices', [
        'device_identifier' => 'unique-device-id-'.uniqid(),
        'device_name' => 'New iPad',
        'platform' => 'ios',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('device.auto_print_receipt', true);
});

it('rejects duplicate device_name in same store', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/pos-devices', [
        'device_identifier' => 'id-a',
        'device_name' => 'POS 4',
        'platform' => 'android',
    ])->assertStatus(201);

    $response = $this->postJson('/api/pos-devices', [
        'device_identifier' => 'id-b',
        'device_name' => 'POS 4',
        'platform' => 'android',
    ]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['device_name']);
});

it('register endpoint creates new device then updates same device by device_name', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);
    Sanctum::actingAs($user, ['*']);

    $payload = [
        'device_identifier' => 'BP2A.250605.031.A3',
        'device_name' => 'POS 4',
        'platform' => 'android',
    ];

    $first = $this->postJson('/api/pos-devices/register', $payload);
    $first->assertStatus(201);
    $first->assertJsonPath('is_new_device', true);
    $deviceId = $first->json('device.id');

    $second = $this->postJson('/api/pos-devices/register', array_merge($payload, ['device_identifier' => 'updated-id']));
    $second->assertStatus(200);
    $second->assertJsonPath('is_new_device', false);
    $second->assertJsonPath('device.id', $deviceId);
    $second->assertJsonPath('device.device_identifier', 'updated-id');
});

it('register endpoint creates separate devices for same identifier different names', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);
    Sanctum::actingAs($user, ['*']);

    $first = $this->postJson('/api/pos-devices/register', [
        'device_identifier' => 'BP2A.250605.031.A3',
        'device_name' => 'POS 4',
        'platform' => 'android',
    ]);
    $first->assertStatus(201);
    $second = $this->postJson('/api/pos-devices/register', [
        'device_identifier' => 'BP2A.250605.031.A3',
        'device_name' => 'POS 6',
        'platform' => 'android',
    ]);
    $second->assertStatus(201);
    expect($first->json('device.id'))->not->toBe($second->json('device.id'));
    expect($first->json('device.device_name'))->toBe('POS 4');
    expect($second->json('device.device_name'))->toBe('POS 6');
});

it('allows same device_identifier for different device_names in same store', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);
    Sanctum::actingAs($user, ['*']);

    $first = $this->postJson('/api/pos-devices', [
        'device_identifier' => 'BP2A.250605.031.A3',
        'device_name' => 'POS 4',
        'platform' => 'android',
    ]);
    $first->assertStatus(201);
    $second = $this->postJson('/api/pos-devices', [
        'device_identifier' => 'BP2A.250605.031.A3',
        'device_name' => 'POS 6',
        'platform' => 'android',
    ]);
    $second->assertStatus(201);
    expect($first->json('device.id'))->not->toBe($second->json('device.id'));
    expect($first->json('device.device_name'))->toBe('POS 4');
    expect($second->json('device.device_name'))->toBe('POS 6');
});

it('allows updating auto_print_receipt via PATCH and returns it on GET', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);
    $device = PosDevice::factory()->create(['store_id' => $store->id]);
    Sanctum::actingAs($user, ['*']);

    $getResponse = $this->getJson("/api/pos-devices/{$device->id}");
    $getResponse->assertOk();
    $getResponse->assertJsonPath('device.auto_print_receipt', true);

    $patchResponse = $this->patchJson("/api/pos-devices/{$device->id}", [
        'auto_print_receipt' => false,
    ]);
    $patchResponse->assertOk();
    $patchResponse->assertJsonPath('device.auto_print_receipt', false);

    $getAgain = $this->getJson("/api/pos-devices/{$device->id}");
    $getAgain->assertJsonPath('device.auto_print_receipt', false);
});
