<?php

namespace App\Filament\Plugins;

use App\Filament\Workflows\WorkflowResource;
use App\Filament\Workflows\WorkflowSecretResource;
use Leek\FilamentWorkflows\WorkflowsPlugin;

class AppWorkflowsPlugin extends WorkflowsPlugin
{
    /**
     * @return array<class-string>
     */
    protected function getResources(): array
    {
        return [
            WorkflowResource::class,
            WorkflowSecretResource::class,
        ];
    }
}
