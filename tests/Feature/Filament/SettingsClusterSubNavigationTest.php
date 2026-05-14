<?php

declare(strict_types=1);

use App\Enums\AddonType;
use App\Filament\Resources\PowerOfficeIntegrations\Pages\ManagePowerOfficeIntegration;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Addon;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'ViewAny:PowerOfficeIntegration', 'guard_name' => 'web']);
    $role->givePermissionTo('ViewAny:PowerOfficeIntegration');

    $this->user = User::factory()->create();
    $this->store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_'.fake()->uuid(),
    ]);
    $this->user->stores()->attach($this->store);
    $this->user->assignRole('super_admin');

    Addon::query()->create([
        'store_id' => $this->store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);

    $this->actingAs($this->user);
    Filament::setTenant($this->store);
    Filament::bootCurrentPanel();
});

it('shows cluster sub-navigation on a custom clustered resource page', function (): void {
    PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $this->store->id,
    ]);

    $component = livewire(ManagePowerOfficeIntegration::class)->assertOk();

    expect($component->instance()->getCachedSubNavigation())->not->toBeEmpty();
});

it('shows cluster sub-navigation on edit record pages in the settings cluster', function (): void {
    $otherUser = User::factory()->create();
    $otherUser->stores()->attach($this->store);

    $component = livewire(EditUser::class, ['record' => $otherUser->getRouteKey()])->assertOk();

    expect($component->instance()->getCachedSubNavigation())->not->toBeEmpty();
});
