<?php

namespace App\Filament\Resources\ConnectedPrices\Pages;

use App\Filament\Resources\ConnectedPrices\ConnectedPriceResource;
use App\Filament\Resources\Pages\ViewRecord;
use Filament\Actions\EditAction;

class ViewConnectedPrice extends ViewRecord
{
    protected static string $resource = ConnectedPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
