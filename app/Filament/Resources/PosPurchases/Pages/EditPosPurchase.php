<?php

namespace App\Filament\Resources\PosPurchases\Pages;

use App\Filament\Resources\PosPurchases\PosPurchaseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPosPurchase extends EditRecord
{
    protected static string $resource = PosPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
