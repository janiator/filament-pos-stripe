<?php

use App\Enums\AddonType;
use App\Filament\Resources\PosPurchases\Pages\ListPosPurchases;
use App\Models\Addon;
use App\Models\ConnectedCharge;
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
    Permission::firstOrCreate(['name' => 'ViewAny:ConnectedCharge', 'guard_name' => 'web']);
    $role->givePermissionTo('ViewAny:ConnectedCharge');

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

it('can load the POS purchases list page', function () {
    livewire(ListPosPurchases::class)
        ->assertOk();
});

it('can filter POS purchases by status', function () {
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'user_id' => $this->user->id,
    ]);
    $succeeded = ConnectedCharge::factory()->create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
    ]);
    $pending = ConnectedCharge::factory()->create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'pending',
    ]);

    livewire(ListPosPurchases::class)
        ->assertCanSeeTableRecords([$succeeded, $pending])
        ->filterTable('status', 'succeeded')
        ->assertCanSeeTableRecords([$succeeded])
        ->assertCanNotSeeTableRecords([$pending]);
});
