<?php

namespace App\Filament\Resources\PosReports\Pages;

use App\Filament\Resources\PosReports\PosReportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPosReport extends EditRecord
{
    protected static string $resource = PosReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
