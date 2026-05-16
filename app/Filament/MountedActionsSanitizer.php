<?php

declare(strict_types=1);

namespace App\Filament;

use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Livewire\Component;

final class MountedActionsSanitizer
{
    /**
     * RichEditor and other schema components can persist entries on {@see HasActions::$mountedActions}
     * that contain only serialized field state (`data`) without a `name`. Filament then throws
     * {@see ActionNotResolvableException} while resolving mounted actions
     * during Livewire dehydrate (modal partial render).
     *
     * @see https://github.com/filamentphp/filament/issues/17096
     */
    public static function sanitizeComponent(Component $component): void
    {
        if (! $component instanceof HasActions) {
            return;
        }

        $mounted = $component->mountedActions ?? null;
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
