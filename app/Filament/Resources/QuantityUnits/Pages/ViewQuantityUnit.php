<?php

namespace App\Filament\Resources\QuantityUnits\Pages;

use App\Filament\Resources\QuantityUnits\QuantityUnitResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

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
