<?php

namespace App\Filament\Resources\PosReports\Pages;

use App\Filament\Resources\PosReports\PosReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPosReports extends ListRecords
{
    protected static string $resource = PosReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
