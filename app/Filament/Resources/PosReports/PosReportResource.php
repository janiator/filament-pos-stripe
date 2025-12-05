<?php

namespace App\Filament\Resources\PosReports;

use App\Filament\Resources\PosReports\Pages\PosReports;
use Filament\Resources\Resource;

class PosReportResource extends Resource
{
    protected static ?string $model = null; // No model, this is a custom page

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.pos_report.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.pos');
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getPages(): array
    {
        return [
            'index' => PosReports::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
