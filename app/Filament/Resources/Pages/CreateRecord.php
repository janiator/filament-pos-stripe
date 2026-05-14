<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Concerns\BuildsClusterWideSubNavigation;
use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;

abstract class CreateRecord extends BaseCreateRecord
{
    use BuildsClusterWideSubNavigation;

    public function getSubNavigation(): array
    {
        return $this->clusterWideSubNavigationMergedWith([]);
    }
}
