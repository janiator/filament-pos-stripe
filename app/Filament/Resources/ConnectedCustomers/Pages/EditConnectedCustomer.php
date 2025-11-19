<?php

namespace App\Filament\Resources\ConnectedCustomers\Pages;

use App\Filament\Resources\ConnectedCustomers\ConnectedCustomerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConnectedCustomer extends EditRecord
{
    protected static string $resource = ConnectedCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
