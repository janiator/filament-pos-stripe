<?php

use App\Models\Store;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\CreateRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->store = Store::factory()->create([
        'slug' => 'tenant-store',
    ]);
});

it('can load the role create page in tenant context', function (): void {
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    Permission::firstOrCreate(['name' => 'Create:Role', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'ViewAny:Role', 'guard_name' => 'web']);

    $role->givePermissionTo(['Create:Role', 'ViewAny:Role']);

    $user = User::factory()->create();
    $user->stores()->attach($this->store);
    $user->assignRole('super_admin');

    $this->actingAs($user);
    Filament::setTenant($this->store);
    Filament::bootCurrentPanel();

    livewire(CreateRole::class)
        ->assertOk();
});

it('can create a global role from the tenant context without team scoping', function (): void {
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    Permission::firstOrCreate(['name' => 'Create:Role', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'ViewAny:Role', 'guard_name' => 'web']);

    $role->givePermissionTo(['Create:Role', 'ViewAny:Role']);

    $user = User::factory()->create();
    $user->stores()->attach($this->store);
    $user->assignRole('super_admin');

    $this->actingAs($user);
    Filament::setTenant($this->store);
    Filament::bootCurrentPanel();

    livewire(CreateRole::class)
        ->fillForm([
            'name' => 'store_operator',
            'guard_name' => 'web',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Role::query()->where('name', 'store_operator')->where('guard_name', 'web')->exists())->toBeTrue();
});

it('allows global super admins to access tenant-protected api routes', function (): void {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    Sanctum::actingAs($superAdmin);

    $this->getJson('/api/pos-events', [
        'X-Tenant' => $this->store->slug,
    ])->assertSuccessful();
});

it('forbids users without tenant access on tenant-protected api routes', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/pos-events', [
        'X-Tenant' => $this->store->slug,
    ])->assertForbidden();
});

it('allows store-attached users on tenant-protected api routes', function (): void {
    $user = User::factory()->create();
    $user->stores()->attach($this->store);

    Sanctum::actingAs($user);

    $this->getJson('/api/pos-events', [
        'X-Tenant' => $this->store->slug,
    ])->assertSuccessful();
});
