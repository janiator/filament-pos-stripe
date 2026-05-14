<?php

namespace App\Filament\Resources\ConnectedPaymentLinks\Pages;

use App\Filament\Resources\ConnectedPaymentLinks\ConnectedPaymentLinkResource;
use App\Filament\Resources\Pages\ViewRecord;
use Filament\Actions\EditAction;

class ViewConnectedPaymentLink extends ViewRecord
{
    protected static string $resource = ConnectedPaymentLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
