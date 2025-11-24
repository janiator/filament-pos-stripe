<?php

namespace App\Filament\Resources\ConnectedPaymentIntents\Pages;

use App\Filament\Resources\ConnectedPaymentIntents\ConnectedPaymentIntentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConnectedPaymentIntent extends ViewRecord
{
    protected static string $resource = ConnectedPaymentIntentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
