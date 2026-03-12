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
