<?php

namespace App\Filament\Resources\TerminalReaders\Pages;

use App\Filament\Resources\TerminalReaders\TerminalReaderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTerminalReader extends EditRecord
{
    protected static string $resource = TerminalReaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
