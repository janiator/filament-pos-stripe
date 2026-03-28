<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class SettingsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog8Tooth;

    public static function getNavigationLabel(): string
    {
        return 'Innstillinger';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.settings');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }
}
