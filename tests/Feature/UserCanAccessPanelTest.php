<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'ViewAny:PosSession', 'guard_name' => 'web']);
});

it('allows panel access when the user belongs to a store and has a resource permission', function (): void {
    $role = Role::firstOrCreate(['name' => 'pos_only', 'guard_name' => 'web']);
    $role->syncPermissions(['ViewAny:PosSession']);

    $user = User::factory()->create();
    $user->stores()->attach(Store::factory()->create());
    $user->assignRole('pos_only');

    $panel = Filament::getPanel('app');

    expect($user->canAccessPanel($panel))->toBeTrue();
});

it('denies panel access when the user has no store', function (): void {
    $role = Role::firstOrCreate(['name' => 'pos_only', 'guard_name' => 'web']);
    $role->syncPermissions(['ViewAny:PosSession']);

    $user = User::factory()->create();
    $user->assignRole('pos_only');

    $panel = Filament::getPanel('app');

    expect($user->canAccessPanel($panel))->toBeFalse();
});

it('denies panel access when the user has a store but no resource permissions', function (): void {
    $user = User::factory()->create();
    $user->stores()->attach(Store::factory()->create());

    $panel = Filament::getPanel('app');

    expect($user->canAccessPanel($panel))->toBeFalse();
});

it('allows super admins to access the panel without a store attachment', function (): void {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $panel = Filament::getPanel('app');

    expect($user->canAccessPanel($panel))->toBeTrue();
});
