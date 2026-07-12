<?php

use App\Filament\Resources\Shield\Roles\RoleResource;

it('does not scope shield roles to the Filament tenant', function (): void {
    expect(RoleResource::isScopedToTenant())->toBeFalse();

    $class = new ReflectionClass(RoleResource::class);

    foreach (['registerTenancyModelGlobalScope', 'observeTenancyModelCreation'] as $methodName) {
        expect($class->hasMethod($methodName))->toBeTrue();
        expect($class->getMethod($methodName)->getDeclaringClass()->getName())->toBe(RoleResource::class);
    }
});
