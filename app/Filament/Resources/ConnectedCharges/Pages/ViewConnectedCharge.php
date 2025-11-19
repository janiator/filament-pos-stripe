<?php

namespace App\Filament\Resources\ConnectedCharges\Pages;

use App\Filament\Resources\ConnectedCharges\ConnectedChargeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConnectedCharge extends ViewRecord
{
    protected static string $resource = ConnectedChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
