<?php

namespace App\Filament\Resources\ReceiptPrinters\Pages;

use App\Filament\Resources\ReceiptPrinters\ReceiptPrinterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReceiptPrinter extends EditRecord
{
    protected static string $resource = ReceiptPrinterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
