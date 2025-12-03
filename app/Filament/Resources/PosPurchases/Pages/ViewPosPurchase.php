<?php

namespace App\Filament\Resources\PosPurchases\Pages;

use App\Filament\Resources\PosPurchases\PosPurchaseResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPosPurchase extends ViewRecord
{
    protected static string $resource = PosPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // POS purchases should not be editable for audit trail compliance
        ];
    }
}
