<?php

namespace App\Filament\Resources\TerminalReaders\Pages;

use App\Filament\Resources\TerminalReaders\TerminalReaderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTerminalReaders extends ListRecords
{
    protected static string $resource = TerminalReaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
