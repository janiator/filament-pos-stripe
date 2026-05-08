<?php

use Filament\Facades\Filament;

it('enables collapsible sidebar on desktop for the app panel', function (): void {
    expect(Filament::getPanel('app')->isSidebarCollapsibleOnDesktop())->toBeTrue();
});
