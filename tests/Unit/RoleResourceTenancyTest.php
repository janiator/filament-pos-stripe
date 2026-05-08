<?php

use App\Filament\Resources\Shield\Roles\RoleResource;

it('does not scope shield roles to the Filament tenant', function (): void {
    expect(RoleResource::isScopedToTenant())->toBeFalse();
});
