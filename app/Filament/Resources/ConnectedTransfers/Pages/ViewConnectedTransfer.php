<?php

namespace App\Filament\Resources\ConnectedTransfers\Pages;

use App\Filament\Resources\ConnectedTransfers\ConnectedTransferResource;
use App\Filament\Resources\Pages\ViewRecord;
use Filament\Actions\EditAction;

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
