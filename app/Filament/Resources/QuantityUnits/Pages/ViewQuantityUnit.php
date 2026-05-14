<?php

namespace App\Filament\Resources\QuantityUnits\Pages;

use App\Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\QuantityUnits\QuantityUnitResource;
use Filament\Actions\EditAction;

class ViewQuantityUnit extends ViewRecord
{
    protected static string $resource = QuantityUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
