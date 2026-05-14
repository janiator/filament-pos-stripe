<?php

namespace App\Filament\Resources\ConnectedSubscriptions\Pages;

use App\Filament\Resources\ConnectedSubscriptions\ConnectedSubscriptionResource;
use App\Filament\Resources\Pages\ViewRecord;
use Filament\Actions\EditAction;

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
