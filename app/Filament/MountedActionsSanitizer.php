<?php

namespace App\Filament;

use Livewire\Component;

class MountedActionsSanitizer
{
    public static function sanitizeComponent(Component $component): void
    {
        if (! property_exists($component, 'mountedActions')) {
            return;
        }

        $mounted = $component->mountedActions;

        if (! is_array($mounted) || $mounted === []) {
            return;
        }

        $sanitized = [];

        foreach ($mounted as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (! filled($entry['name'] ?? null)) {
                continue;
            }

            $sanitized[] = $entry;
        }

        if (count($sanitized) !== count($mounted)) {
            $component->mountedActions = $sanitized;
        }
    }
}
