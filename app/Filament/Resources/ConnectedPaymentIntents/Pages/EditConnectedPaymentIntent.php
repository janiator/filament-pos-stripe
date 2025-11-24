<?php

namespace App\Filament\Resources\ConnectedPaymentIntents\Pages;

use App\Filament\Resources\ConnectedPaymentIntents\ConnectedPaymentIntentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConnectedPaymentIntent extends EditRecord
{
    protected static string $resource = ConnectedPaymentIntentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
