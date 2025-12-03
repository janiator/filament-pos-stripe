<?php

namespace App\Filament\Resources\PosPurchases\Pages;

use App\Filament\Resources\PosPurchases\PosPurchaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPosPurchases extends ListRecords
{
    protected static string $resource = PosPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // POS purchases should only be created via API, not manually
            // This ensures compliance with kassasystemforskriften
        ];
    }
}
