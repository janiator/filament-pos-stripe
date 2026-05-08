<?php

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('clears oauth token fields on the store integration', function () {
    $store = Store::factory()->create(['slug' => 'token-store']);
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);
    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'access_token' => 'old-token',
        'token_expires_at' => now()->addHour(),
    ]);

    $this->artisan('poweroffice:forget-token', ['store_slug' => 'token-store'])
        ->assertSuccessful();

    $integration->refresh();
    expect($integration->access_token)->toBeNull()
        ->and($integration->token_expires_at)->toBeNull();
});
