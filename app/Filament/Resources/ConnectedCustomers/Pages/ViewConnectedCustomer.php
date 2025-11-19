<?php

namespace App\Filament\Resources\ConnectedCustomers\Pages;

use App\Filament\Resources\ConnectedCustomers\ConnectedCustomerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConnectedCustomer extends ViewRecord
{
    protected static string $resource = ConnectedCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
