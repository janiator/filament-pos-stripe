<?php

namespace App\Filament\Resources\ConnectedProducts\Pages;

use App\Filament\Resources\ConnectedProducts\ConnectedProductResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConnectedProduct extends ViewRecord
{
    protected static string $resource = ConnectedProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
