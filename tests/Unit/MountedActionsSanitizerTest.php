<?php

use App\Filament\MountedActionsSanitizer;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Livewire\Component;

it('removes mounted action entries that are missing a name', function (): void {
    $component = new class extends Component implements HasActions
    {
        use InteractsWithActions;
    };

    $component->mountedActions = [
        ['data' => ['description' => ['type' => 'doc', 'content' => []]]],
    ];

    MountedActionsSanitizer::sanitizeComponent($component);

    expect($component->mountedActions)->toBe([]);
});

it('keeps only entries that declare a non-blank name', function (): void {
    $component = new class extends Component implements HasActions
    {
        use InteractsWithActions;
    };

    $component->mountedActions = [
        ['data' => ['description' => ['type' => 'doc', 'content' => []]]],
        ['name' => 'generateSkus', 'arguments' => [], 'context' => []],
    ];

    MountedActionsSanitizer::sanitizeComponent($component);

    expect($component->mountedActions)->toHaveCount(1)
        ->and($component->mountedActions[0]['name'] ?? null)->toBe('generateSkus');
});

it('does not modify components that do not expose mounted actions', function (): void {
    $component = new class extends Component {};

    MountedActionsSanitizer::sanitizeComponent($component);

    expect(property_exists($component, 'mountedActions'))->toBeFalse();
});
