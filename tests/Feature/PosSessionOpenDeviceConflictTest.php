<?php

use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns 409 when opening a session on a device that has an open session on another store', function () {
    $user = User::factory()->create();
    $storeA = Store::factory()->create(['slug' => 'store-a']);
    $storeB = Store::factory()->create(['slug' => 'store-b']);
    $user->stores()->attach([$storeA->id, $storeB->id]);
    $user->setCurrentStore($storeA);

    $device = PosDevice::factory()->create(['store_id' => $storeA->id]);

    Sanctum::actingAs($user, ['*']);

    // Open session on store A with this device
    $openA = $this->postJson('/api/pos-sessions/open', [
        'pos_device_id' => $device->id,
        'opening_balance' => 0,
    ]);
    $openA->assertStatus(201);

    // Try to open a session on store B with the same device (without closing store A session)
    $openB = $this->withHeader('X-Tenant', $storeB->slug)
        ->postJson('/api/pos-sessions/open', [
            'pos_device_id' => $device->id,
            'opening_balance' => 0,
        ]);

    $openB->assertStatus(409)
        ->assertJson([
            'message' => 'You need to close other open POS sessions on the current device before opening a new session.',
        ])
        ->assertJsonPath('session.id', PosSession::where('pos_device_id', $device->id)->where('status', 'open')->first()->id);
});

it('returns 409 with device already has open session when opening again for same store', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);
    $device = PosDevice::factory()->create(['store_id' => $store->id]);

    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/pos-sessions/open', [
        'pos_device_id' => $device->id,
        'opening_balance' => 0,
    ])->assertStatus(201);

    $response = $this->postJson('/api/pos-sessions/open', [
        'pos_device_id' => $device->id,
        'opening_balance' => 0,
    ]);

    $response->assertStatus(409)
        ->assertJson([
            'message' => 'Device already has an open session',
        ]);
});
