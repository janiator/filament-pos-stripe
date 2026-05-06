<?php

namespace App\Filament\Workflows;

use App\Filament\Clusters\SettingsCluster;

class WorkflowSecretResource extends \Leek\FilamentWorkflows\Resources\WorkflowSecretResource
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static bool $shouldRegisterNavigation = false;
}
