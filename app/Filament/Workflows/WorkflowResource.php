<?php

namespace App\Filament\Workflows;

use App\Enums\AddonType;
use App\Filament\Clusters\SettingsCluster;
use App\Models\Addon;
use Filament\Facades\Filament;

class WorkflowResource extends \Leek\FilamentWorkflows\Resources\WorkflowResource
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $slug = 'workflows';

    public static function shouldRegisterNavigation(): bool
    {
        if (! parent::shouldRegisterNavigation()) {
            return false;
        }

        return Addon::storeHasActiveAddon(Filament::getTenant()?->getKey(), AddonType::Workflows);
    }
}
