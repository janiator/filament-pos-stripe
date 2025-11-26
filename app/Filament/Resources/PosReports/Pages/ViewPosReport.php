<?php

namespace App\Filament\Resources\PosReports\Pages;

use App\Filament\Resources\PosReports\PosReportResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPosReport extends ViewRecord
{
    protected static string $resource = PosReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
