<?php

namespace App\Filament\Resources\TerminalLocations\Pages;

use App\Filament\Resources\TerminalLocations\TerminalLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTerminalLocations extends ListRecords
{
    protected static string $resource = TerminalLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
