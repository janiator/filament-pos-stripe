<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Concerns\BuildsClusterWideSubNavigation;
use Filament\Resources\Pages\EditRecord as BaseEditRecord;

abstract class EditRecord extends BaseEditRecord
{
    use BuildsClusterWideSubNavigation;

    public function getSubNavigation(): array
    {
        return $this->clusterWideSubNavigationMergedWith(
            static::getResource()::getRecordSubNavigation($this),
        );
    }
}
