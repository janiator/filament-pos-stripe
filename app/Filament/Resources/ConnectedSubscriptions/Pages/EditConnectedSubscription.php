<?php

namespace App\Filament\Resources\ConnectedSubscriptions\Pages;

use App\Filament\Resources\ConnectedSubscriptions\ConnectedSubscriptionResource;
use App\Filament\Resources\Pages\EditRecord;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

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
