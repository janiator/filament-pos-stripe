<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;

test('nb json translates common filament labels', function (): void {
    app()->setLocale('nb');

    expect(__('Name'))->toBe('Navn');
    expect(__('Email'))->toBe('E-post');
    expect(__('Store'))->toBe('Butikk');
    expect(__('Product declaration'))->toBe('Produktdeklarasjon');
});

test('filament shield has norwegian vendor translations', function (): void {
    app()->setLocale('nb');

    expect(__('filament-shield::filament-shield.column.name'))->toBe('Navn');
    expect(__('filament-shield::filament-shield.resource.label.roles'))->toBe('Roller');
    expect(__('filament-shield::filament-shield.resource_permission_prefixes_labels.view'))->toBe('Vis');
    expect(__('filament-shield::filament-shield.resource_permission_prefixes_labels.view_any'))->toBe('Vis alle');
});

test('filament php translations resolve for nb locale', function (): void {
    app()->setLocale('nb');

    expect(__('filament.navigation_groups.settings'))->toBe('Innstillinger');
    expect(__('filament.resources.connected_product.navigation'))->toBe('Produkter');
});

test('filament shield nb translation file is registered', function (): void {
    app()->setLocale('nb');

    expect(Lang::has('filament-shield::filament-shield.column.name'))->toBeTrue();
});
