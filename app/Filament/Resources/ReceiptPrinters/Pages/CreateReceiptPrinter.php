<?php

namespace App\Filament\Resources\ReceiptPrinters\Pages;

use App\Filament\Resources\ReceiptPrinters\ReceiptPrinterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReceiptPrinter extends CreateRecord
{
    protected static string $resource = ReceiptPrinterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set the store_id to the current tenant
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant) {
            $data['store_id'] = $tenant->id;
        }

        return $data;
    }
}
