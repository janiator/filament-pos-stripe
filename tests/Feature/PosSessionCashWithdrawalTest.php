<?php

use App\Models\PosDevice;
use App\Models\PosEvent;
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

it('records cash withdrawal for open session and returns 201 with event', function () {
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
        'opening_balance' => 10000,
    ]);

    $response = $this->postJson("/api/pos-sessions/{$session->id}/cash-withdrawal", [
        'amount' => 5000,
        'reason' => 'Bank deposit',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Cash withdrawal recorded',
            'event' => [
                'event_code' => PosEvent::EVENT_CASH_WITHDRAWAL,
                'event_data' => [
                    'amount' => 5000,
                    'reason' => 'Bank deposit',
                ],
            ],
        ]);
    $response->assertJsonPath('event.id', fn ($id) => $id > 0);

    $this->assertDatabaseHas('pos_events', [
        'pos_session_id' => $session->id,
        'event_code' => PosEvent::EVENT_CASH_WITHDRAWAL,
        'event_type' => 'drawer',
    ]);
});

it('records cash deposit for open session and returns 201 with event', function () {
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
        'opening_balance' => 10000,
    ]);

    $response = $this->postJson("/api/pos-sessions/{$session->id}/cash-deposit", [
        'amount' => 3000,
        'reason' => 'Change refill',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Cash deposit recorded',
            'event' => [
                'event_code' => PosEvent::EVENT_CASH_DEPOSIT,
                'event_data' => [
                    'amount' => 3000,
                    'reason' => 'Change refill',
                ],
            ],
        ]);

    $this->assertDatabaseHas('pos_events', [
        'pos_session_id' => $session->id,
        'event_code' => PosEvent::EVENT_CASH_DEPOSIT,
        'event_type' => 'drawer',
    ]);
});

it('rejects cash withdrawal when session is closed', function () {
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'closed',
        'opening_balance' => 10000,
    ]);

    $response = $this->postJson("/api/pos-sessions/{$session->id}/cash-withdrawal", [
        'amount' => 5000,
    ]);

    $response->assertStatus(400)
        ->assertJson(['message' => 'Cash withdrawal can only be recorded for open sessions.']);
});

it('rejects cash deposit when session is closed', function () {
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'closed',
    ]);

    $response = $this->postJson("/api/pos-sessions/{$session->id}/cash-deposit", [
        'amount' => 1000,
    ]);

    $response->assertStatus(400)
        ->assertJson(['message' => 'Cash deposit can only be recorded for open sessions.']);
});

it('rejects cash withdrawal when amount is invalid', function () {
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);

    $response = $this->postJson("/api/pos-sessions/{$session->id}/cash-withdrawal", [
        'amount' => 0,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

it('decreases expected cash after withdrawal and increases after deposit', function () {
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
        'opening_balance' => 20000,
    ]);

    $expectedBefore = $session->calculateExpectedCash();
    expect($expectedBefore)->toBe(20000);

    $this->postJson("/api/pos-sessions/{$session->id}/cash-withdrawal", [
        'amount' => 5000,
    ])->assertStatus(201);

    $session->refresh();
    $session->load(['events']);
    $expectedAfterWithdrawal = $session->calculateExpectedCash();
    expect($expectedAfterWithdrawal)->toBe(15000);

    $this->postJson("/api/pos-sessions/{$session->id}/cash-deposit", [
        'amount' => 2000,
    ])->assertStatus(201);

    $session->refresh();
    $session->load(['events']);
    $expectedAfterDeposit = $session->calculateExpectedCash();
    expect($expectedAfterDeposit)->toBe(17000);
});

it('includes cash_withdrawals and cash_deposits in X-report when present', function () {
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
        'opening_balance' => 10000,
    ]);

    $this->postJson("/api/pos-sessions/{$session->id}/cash-withdrawal", ['amount' => 2000])->assertStatus(201);
    $this->postJson("/api/pos-sessions/{$session->id}/cash-deposit", ['amount' => 1000])->assertStatus(201);

    $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);
    $report = \App\Filament\Resources\PosSessions\Tables\PosSessionsTable::generateXReport($session);

    expect($report)->toHaveKeys(['cash_withdrawals', 'cash_deposits']);
    expect($report['cash_withdrawals']['count'])->toBe(1);
    expect($report['cash_withdrawals']['total_amount'])->toBe(2000);
    expect($report['cash_deposits']['count'])->toBe(1);
    expect($report['cash_deposits']['total_amount'])->toBe(1000);
});
