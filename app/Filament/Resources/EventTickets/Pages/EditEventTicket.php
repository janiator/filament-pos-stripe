<?php

namespace App\Filament\Resources\EventTickets\Pages;

use App\Filament\Resources\EventTickets\EventTicketResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEventTicket extends EditRecord
{
    protected static string $resource = EventTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
