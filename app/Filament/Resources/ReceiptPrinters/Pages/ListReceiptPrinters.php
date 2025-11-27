<?php

namespace App\Filament\Resources\ReceiptPrinters\Pages;

use App\Filament\Resources\ReceiptPrinters\ReceiptPrinterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReceiptPrinters extends ListRecords
{
    protected static string $resource = ReceiptPrinterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
