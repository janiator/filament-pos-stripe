<?php

namespace App\Filament\Resources\ConnectedPaymentMethods\Pages;

use App\Filament\Resources\ConnectedPaymentMethods\ConnectedPaymentMethodResource;
use App\Filament\Resources\Pages\ViewRecord;
use Filament\Actions\EditAction;

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
