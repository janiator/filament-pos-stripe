<?php

use App\Models\PosDevice;
use App\Models\Store;
use App\Models\TerminalLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

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

