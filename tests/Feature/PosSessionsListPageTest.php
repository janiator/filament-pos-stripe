<?php

use App\Enums\AddonType;
use App\Filament\Resources\PosSessions\Pages\ListPosSessions;
use App\Models\Addon;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'ViewAny:PosSession', 'guard_name' => 'web']);
    $role->givePermissionTo('ViewAny:PosSession');

    $this->user = User::factory()->create();
    $this->store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_'.fake()->uuid(),
    ]);
    $this->user->stores()->attach($this->store);
    $this->user->assignRole('super_admin');

    Addon::factory()->create([
        'store_id' => $this->store->id,
        'type' => AddonType::Pos,
        'is_active' => true,
    ]);

    $this->actingAs($this->user);
    Filament::setTenant($this->store);
    Filament::bootCurrentPanel();
});

it('shows both open and closed sessions by default', function () {
    $openSession = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'user_id' => $this->user->id,
        'status' => 'open',
        'closed_at' => null,
    ]);

    $closedSession = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'user_id' => $this->user->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    livewire(ListPosSessions::class)
        ->assertCanSeeTableRecords([$openSession, $closedSession]);
});

it('does not expose a create action on the sessions list page', function () {
    livewire(ListPosSessions::class)
        ->assertActionDoesNotExist('create');
});
