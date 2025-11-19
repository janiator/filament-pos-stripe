<?php

namespace App\Filament\Resources\ConnectedSubscriptions\Pages;

use App\Filament\Resources\ConnectedSubscriptions\ConnectedSubscriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConnectedSubscriptions extends ListRecords
{
    protected static string $resource = ConnectedSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
