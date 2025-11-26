<?php

namespace App\Filament\Resources\PosEvents\Pages;

use App\Filament\Resources\PosEvents\PosEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPosEvents extends ListRecords
{
    protected static string $resource = PosEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
