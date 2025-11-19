<?php

namespace App\Filament\Resources\ConnectedPaymentLinks\Pages;

use App\Filament\Resources\ConnectedPaymentLinks\ConnectedPaymentLinkResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

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
