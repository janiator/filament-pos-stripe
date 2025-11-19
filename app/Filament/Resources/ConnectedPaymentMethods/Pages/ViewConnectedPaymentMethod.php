<?php

namespace App\Filament\Resources\ConnectedPaymentMethods\Pages;

use App\Filament\Resources\ConnectedPaymentMethods\ConnectedPaymentMethodResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConnectedPaymentMethod extends ViewRecord
{
    protected static string $resource = ConnectedPaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
