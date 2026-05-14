<?php

namespace App\Filament\Resources\TerminalLocations\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\TerminalLocations\TerminalLocationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditTerminalLocation extends EditRecord
{
    protected static string $resource = TerminalLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
