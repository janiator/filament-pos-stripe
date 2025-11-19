<?php

namespace App\Filament\Resources\ConnectedTransfers\Pages;

use App\Filament\Resources\ConnectedTransfers\ConnectedTransferResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConnectedTransfer extends ViewRecord
{
    protected static string $resource = ConnectedTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
