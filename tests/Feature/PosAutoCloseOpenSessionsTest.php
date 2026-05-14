<?php

use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->store = Store::factory()->create();
    $this->user = User::factory()->create();
    $this->device = PosDevice::factory()->create(['store_id' => $this->store->id]);
});

it('closes open sessions when store toggle is on without any global master flag', function (): void {
    Config::set('pos.auto_close_sessions.closing_notes', 'Scheduled auto-close.');

    Setting::getForStore($this->store->id)->update(['auto_close_open_sessions_daily' => true]);

    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
        'session_number' => '900001',
        'opening_balance' => 0,
    ]);

    $this->artisan('pos:auto-close-open-sessions')
        ->assertSuccessful();

    $session->refresh();
    expect($session->status)->toBe('closed');
    expect($session->closing_notes)->toBe('Scheduled auto-close.');
});

it('does not close sessions when store toggle is off', function (): void {
    Config::set('pos.auto_close_sessions.closing_notes', 'Scheduled auto-close.');

    Setting::getForStore($this->store->id)->update(['auto_close_open_sessions_daily' => false]);

    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
        'session_number' => '900004',
    ]);

    $this->artisan('pos:auto-close-open-sessions')
        ->assertSuccessful();

    expect($session->fresh()->status)->toBe('open');
});

it('closes open sessions with --force even when store toggle is off', function (): void {
    Config::set('pos.auto_close_sessions.closing_notes', 'Forced auto-close.');

    Setting::getForStore($this->store->id)->update(['auto_close_open_sessions_daily' => false]);

    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
        'session_number' => '900003',
    ]);

    $this->artisan('pos:auto-close-open-sessions', ['--force' => true])
        ->assertSuccessful();

    expect($session->fresh()->status)->toBe('closed');
});
