<?php

use App\Models\PosDevice;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns existing device after unique violation on store_id and device_name', function (): void {
    $store = Store::factory()->create();
    $existing = PosDevice::factory()->create([
        'store_id' => $store->id,
        'device_name' => 'Register Race Tablet',
        'device_identifier' => 'concurrent-a',
        'platform' => 'android',
    ]);

    $validated = [
        'store_id' => $store->id,
        'device_identifier' => 'concurrent-b',
        'device_name' => 'Register Race Tablet',
        'platform' => 'android',
        'device_model' => null,
        'device_brand' => null,
        'device_manufacturer' => null,
        'device_product' => null,
        'device_hardware' => null,
        'machine_identifier' => null,
        'system_name' => null,
        'system_version' => null,
        'vendor_identifier' => null,
        'android_id' => null,
        'serial_number' => null,
        'device_status' => 'active',
        'last_seen_at' => now(),
        'device_metadata' => null,
        'cash_drawer_enabled' => true,
        'has_integrated_drawer' => false,
        'booking_enabled' => false,
        'auto_print_receipt' => true,
    ];

    [$device, $inserted] = PosDevice::createOrFetchForIdempotentRegister(
        $validated,
        $store->id,
        'Register Race Tablet',
    );

    expect($inserted)->toBeFalse();
    expect($device->is($existing))->toBeTrue();
    expect(PosDevice::query()->where('store_id', $store->id)->count())->toBe(1);
});

it('inserts a new row when no conflicting store and device_name exists', function (): void {
    $store = Store::factory()->create();

    $validated = [
        'store_id' => $store->id,
        'device_identifier' => 'new-android-id',
        'device_name' => 'Fresh Tablet',
        'platform' => 'android',
        'device_model' => null,
        'device_brand' => null,
        'device_manufacturer' => null,
        'device_product' => null,
        'device_hardware' => null,
        'machine_identifier' => null,
        'system_name' => null,
        'system_version' => null,
        'vendor_identifier' => null,
        'android_id' => null,
        'serial_number' => null,
        'device_status' => 'active',
        'last_seen_at' => now(),
        'device_metadata' => null,
        'cash_drawer_enabled' => true,
        'has_integrated_drawer' => false,
        'booking_enabled' => false,
        'auto_print_receipt' => true,
    ];

    [$device, $inserted] = PosDevice::createOrFetchForIdempotentRegister(
        $validated,
        $store->id,
        'Fresh Tablet',
    );

    expect($inserted)->toBeTrue();
    expect($device->exists)->toBeTrue();
    expect(PosDevice::query()->where('store_id', $store->id)->count())->toBe(1);
});
