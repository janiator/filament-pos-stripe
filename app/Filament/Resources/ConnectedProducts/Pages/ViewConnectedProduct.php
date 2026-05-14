<?php

namespace App\Filament\Resources\ConnectedProducts\Pages;

use App\Filament\Resources\ConnectedProducts\ConnectedProductResource;
use App\Filament\Resources\Pages\ViewRecord;
use Filament\Actions\EditAction;

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
