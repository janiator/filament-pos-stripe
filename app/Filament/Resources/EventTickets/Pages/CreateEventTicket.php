<?php

namespace App\Filament\Resources\EventTickets\Pages;

use App\Filament\Resources\EventTickets\EventTicketResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEventTicket extends CreateRecord
{
    protected static string $resource = EventTicketResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();
        if ($tenant) {
            $data['store_id'] = $tenant->getKey();
        }

        return $data;
    }
}
