<?php

namespace App\Filament\Resources\ConnectedPrices\Pages;

use App\Filament\Resources\ConnectedPrices\ConnectedPriceResource;
use App\Filament\Resources\Pages\EditRecord;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditConnectedPrice extends EditRecord
{
    protected static string $resource = ConnectedPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
