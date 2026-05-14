<?php

namespace App\Filament\Resources\TerminalLocations\Pages;

use App\Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\TerminalLocations\TerminalLocationResource;
use Filament\Actions\EditAction;

class ViewTerminalLocation extends ViewRecord
{
    protected static string $resource = TerminalLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
