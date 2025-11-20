<?php

namespace App\Filament\Resources\TerminalReaders\Pages;

use App\Filament\Resources\TerminalReaders\TerminalReaderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTerminalReader extends ViewRecord
{
    protected static string $resource = TerminalReaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
