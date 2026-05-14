<?php

namespace App\Filament\Resources\ConnectedPaymentIntents\Pages;

use App\Filament\Resources\ConnectedPaymentIntents\ConnectedPaymentIntentResource;
use App\Filament\Resources\Pages\EditRecord;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

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
