<?php

namespace App\Filament\Resources\QuantityUnits\Pages;

use App\Filament\Resources\QuantityUnits\QuantityUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuantityUnits extends ListRecords
{
    protected static string $resource = QuantityUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
