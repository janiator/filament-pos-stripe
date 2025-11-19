<?php

namespace App\Filament\Resources\ConnectedSubscriptions\Pages;

use App\Filament\Resources\ConnectedSubscriptions\ConnectedSubscriptionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConnectedSubscription extends EditRecord
{
    protected static string $resource = ConnectedSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
