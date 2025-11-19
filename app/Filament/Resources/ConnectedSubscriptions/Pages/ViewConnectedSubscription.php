<?php

namespace App\Filament\Resources\ConnectedSubscriptions\Pages;

use App\Filament\Resources\ConnectedSubscriptions\ConnectedSubscriptionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConnectedSubscription extends ViewRecord
{
    protected static string $resource = ConnectedSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
