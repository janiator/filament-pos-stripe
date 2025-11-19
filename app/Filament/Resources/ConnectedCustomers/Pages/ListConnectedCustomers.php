<?php

namespace App\Filament\Resources\ConnectedCustomers\Pages;

use App\Filament\Resources\ConnectedCustomers\ConnectedCustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConnectedCustomers extends ListRecords
{
    protected static string $resource = ConnectedCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
