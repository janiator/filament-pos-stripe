<?php

namespace App\Filament\Resources\EventTickets\Pages;

use App\Filament\Resources\EventTickets\EventTicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventTickets extends ListRecords
{
    protected static string $resource = EventTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
