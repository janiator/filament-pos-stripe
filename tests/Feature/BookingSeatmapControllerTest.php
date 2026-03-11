<?php

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\PosDevice;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('booking seatmap route redirects to configured merano url', function () {
    $store = Store::factory()->create([
        'slug' => 'demo-store',
        'merano_base_url' => 'https://merano.example.com',
        'merano_pos_api_token' => 'merano-token',
    ]);

    Addon::factory()->for($store)->create([
        'type' => AddonType::MeranoBooking,
        'is_active' => true,
    ]);

    $device = PosDevice::factory()->create([
        'store_id' => $store->id,
        'booking_enabled' => true,
    ]);

    $response = $this->get('/booking/seatmap?tenant=demo-store&provider=merano&action=book&storageKey=positiv_action_result&posDeviceId='.$device->id);

    $response->assertRedirect('https://merano.example.com/booking/seatmap?tenant=demo-store&provider=merano&action=book&storageKey=positiv_action_result&posDeviceId='.$device->id.'&posToken=merano-token');
});

test('booking seatmap route does not duplicate seatmap path when already configured', function () {
    $store = Store::factory()->create([
        'slug' => 'demo-store',
        'merano_base_url' => 'https://merano.example.com/booking/seatmap',
        'merano_pos_api_token' => 'merano-token',
    ]);

    Addon::factory()->for($store)->create([
        'type' => AddonType::MeranoBooking,
        'is_active' => true,
    ]);

    $response = $this->get('/booking/seatmap?tenant=demo-store&provider=merano&action=book&storageKey=positiv_action_result');

    $response->assertRedirect('https://merano.example.com/booking/seatmap?tenant=demo-store&provider=merano&action=book&storageKey=positiv_action_result&posToken=merano-token');
});

test('booking seatmap route resolves tenant by stripe account id', function () {
    $store = Store::factory()->create([
        'slug' => 'demo-store',
        'stripe_account_id' => 'acct-1qkiscru3ljbb32r',
        'merano_base_url' => 'https://merano.example.com',
        'merano_pos_api_token' => 'merano-token',
    ]);

    Addon::factory()->for($store)->create([
        'type' => AddonType::MeranoBooking,
        'is_active' => true,
    ]);

    $response = $this->get('/booking/seatmap?tenant=acct-1qkiscru3ljbb32r&provider=merano&action=book&storageKey=merano_pos_seatmap_order');

    $response->assertRedirect('https://merano.example.com/booking/seatmap?tenant=acct-1qkiscru3ljbb32r&provider=merano&action=book&storageKey=merano_pos_seatmap_order&posToken=merano-token');
});

test('booking seatmap route returns service unavailable when addon is inactive', function () {
    $store = Store::factory()->create([
        'slug' => 'demo-store',
        'merano_base_url' => 'https://merano.example.com',
        'merano_pos_api_token' => 'merano-token',
    ]);

    $response = $this->get('/booking/seatmap?tenant=demo-store&provider=merano&action=book');

    $response->assertServiceUnavailable();
    expect($response->getContent())->toContain('Merano booking is not enabled for this store.');
});

test('booking seatmap route returns forbidden when device booking is disabled', function () {
    $store = Store::factory()->create([
        'slug' => 'demo-store',
        'merano_base_url' => 'https://merano.example.com',
        'merano_pos_api_token' => 'merano-token',
    ]);

    Addon::factory()->for($store)->create([
        'type' => AddonType::MeranoBooking,
        'is_active' => true,
    ]);

    $device = PosDevice::factory()->create([
        'store_id' => $store->id,
        'booking_enabled' => false,
    ]);

    $response = $this->get('/booking/seatmap?tenant=demo-store&provider=merano&action=book&posDeviceId='.$device->id);

    $response->assertForbidden();
    expect($response->getContent())->toContain('Booking is not enabled for this device.');
});
