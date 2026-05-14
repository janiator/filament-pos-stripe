<?php

namespace App\Filament\Resources\ConnectedCharges\Pages;

use App\Filament\Resources\ConnectedCharges\ConnectedChargeResource;
use App\Filament\Resources\Pages\ViewRecord;
use Filament\Actions\EditAction;

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
