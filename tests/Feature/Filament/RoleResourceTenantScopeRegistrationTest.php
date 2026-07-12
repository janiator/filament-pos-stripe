<?php

declare(strict_types=1);

use App\Filament\Resources\Shield\Roles\RoleResource;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
    $this->store = Store::factory()->create();
});

it('does not register Filament tenant global scopes on Spatie Permission roles', function (): void {
    $panel = Filament::getPanel('app');

    expect(Role::hasGlobalScope($panel->getTenancyScopeName()))->toBeFalse()
        ->and(RoleResource::isScopedToTenant())->toBeFalse();
});

it('does not crash when querying the shield Role resource eloquent builder with an active tenant', function (): void {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->store);
    Filament::bootCurrentPanel();

    expect(fn (): int => RoleResource::getEloquentQuery()->count())->not->toThrow(Throwable::class);
});
