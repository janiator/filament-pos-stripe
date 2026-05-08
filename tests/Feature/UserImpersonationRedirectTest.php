<?php

declare(strict_types=1);

use App\Filament\Resources\PosSessions\PosSessionResource;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('redirects impersonation targets with dashboard permission to their first store dashboard', function (): void {
    Permission::firstOrCreate(['name' => 'View:Dashboard', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'with_dashboard', 'guard_name' => 'web']);
    $role->givePermissionTo('View:Dashboard');

    $store = Store::factory()->create(['slug' => 'test-butikk']);
    $user = User::factory()->create();
    $user->stores()->attach($store);
    $user->assignRole('with_dashboard');

    expect($user->impersonationRedirectUrl())->toBe(route('filament.app.pages.dashboard', ['tenant' => $store]));
});

it('redirects impersonation targets with only pos session access to the pos sessions index', function (): void {
    Permission::firstOrCreate(['name' => 'ViewAny:PosSession', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'pos_only', 'guard_name' => 'web']);
    $role->givePermissionTo('ViewAny:PosSession');

    $store = Store::factory()->create(['slug' => 'pos-butikk']);
    $user = User::factory()->create();
    $user->stores()->attach($store);
    $user->assignRole('pos_only');

    expect($user->impersonationRedirectUrl())->toBe(PosSessionResource::getUrl('index', [], true, 'app', $store));
});

it('redirects impersonation targets without usable panel access to the panel profile route', function (): void {
    $store = Store::factory()->create(['slug' => 'no-perm-butikk']);
    $user = User::factory()->create();
    $user->stores()->attach($store);

    expect($user->impersonationRedirectUrl())->toBe(route('filament.app.auth.profile'));
});

it('redirects impersonation targets without stores to the panel profile route', function (): void {
    $user = User::factory()->create();

    expect($user->impersonationRedirectUrl())->toBe(route('filament.app.auth.profile'));
});
