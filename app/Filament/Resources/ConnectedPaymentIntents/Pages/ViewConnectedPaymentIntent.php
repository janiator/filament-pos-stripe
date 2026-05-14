<?php

namespace App\Filament\Resources\ConnectedPaymentIntents\Pages;

use App\Filament\Resources\ConnectedPaymentIntents\ConnectedPaymentIntentResource;
use App\Filament\Resources\Pages\ViewRecord;
use Filament\Actions\EditAction;

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
