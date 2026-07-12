<?php

declare(strict_types=1);

use App\Filament\MountedActionsSanitizer;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Livewire\Component;
use Tests\TestCase;

uses(TestCase::class);

it('removes mounted action entries without a name', function (): void {
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
