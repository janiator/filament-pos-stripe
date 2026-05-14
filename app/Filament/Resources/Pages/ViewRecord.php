<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Concerns\BuildsClusterWideSubNavigation;
use Filament\Resources\Pages\ViewRecord as BaseViewRecord;

abstract class ViewRecord extends BaseViewRecord
{
    use BuildsClusterWideSubNavigation;

    public function getSubNavigation(): array
    {
        return $this->clusterWideSubNavigationMergedWith(
            static::getResource()::getRecordSubNavigation($this),
        );
    }
}
