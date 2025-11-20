<?php

namespace App\Filament\Resources\TerminalLocations\Pages;

use App\Filament\Resources\TerminalLocations\TerminalLocationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

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
