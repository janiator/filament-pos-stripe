<?php

namespace App\Filament\Resources\PowerOfficeAccountMappings\Pages;

use App\Filament\Resources\PowerOfficeAccountMappings\PowerOfficeAccountMappingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPowerOfficeAccountMappings extends ListRecords
{
    protected static string $resource = PowerOfficeAccountMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
