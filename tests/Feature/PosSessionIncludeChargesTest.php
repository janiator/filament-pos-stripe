<?php

use App\Models\ConnectedCharge;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = Store::factory()->create();
    $this->user->stores()->attach($this->store);
    $this->user->setCurrentStore($this->store);
    $this->device = PosDevice::factory()->create(['store_id' => $this->store->id]);
    Sanctum::actingAs($this->user, ['*']);
});

it('omits session_charges by default for current session', function () {
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);

    $response = $this->getJson('/api/pos-sessions/current?pos_device_id='.$this->device->id);

    $response->assertOk();
    $response->assertJsonMissingPath('session_charges');
    $response->assertJsonPath('id', $session->id);
});

it('includes session_charges when include_session_charges is true for current session', function () {
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);
    ConnectedCharge::factory()->create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'pos_session_id' => $session->id,
        'amount' => 5000,
        'status' => 'succeeded',
    ]);

    $response = $this->getJson('/api/pos-sessions/current?pos_device_id='.$this->device->id.'&include_session_charges=true');

    $response->assertOk();
    $response->assertJsonPath('session_charges.0.amount', 5000);
});

it('omits session_charges by default for show session', function () {
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'closed',
    ]);

    $response = $this->getJson('/api/pos-sessions/'.$session->id);

    $response->assertOk();
    $response->assertJsonMissingPath('session.session_charges');
});

it('includes session_charges when include_session_charges is true for show session', function () {
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'closed',
    ]);
    ConnectedCharge::factory()->create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'pos_session_id' => $session->id,
        'amount' => 10000,
        'status' => 'succeeded',
    ]);

    $response = $this->getJson('/api/pos-sessions/'.$session->id.'?include_session_charges=true');

    $response->assertOk();
    $response->assertJsonPath('session.session_charges.0.amount', 10000);
});

it('omits session_charges in list by default', function () {
    PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);

    $response = $this->getJson('/api/pos-sessions');

    $response->assertOk();
    $first = $response->json('sessions.0');
    expect($first)->not->toHaveKey('session_charges');
});
