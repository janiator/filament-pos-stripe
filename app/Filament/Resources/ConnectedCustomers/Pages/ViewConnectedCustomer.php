<?php

namespace App\Filament\Resources\ConnectedCustomers\Pages;

use App\Filament\Resources\ConnectedCustomers\ConnectedCustomerResource;
use App\Filament\Resources\Pages\ViewRecord;
use App\Models\ConnectedCustomer;
use Filament\Actions\EditAction;

class ViewConnectedCustomer extends ViewRecord
{
    protected static string $resource = ConnectedCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->hidden(fn (ConnectedCustomer $record): bool => $record->isArchived()),
        ];
    }
}
