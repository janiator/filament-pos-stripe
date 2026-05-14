<?php

namespace App\Filament\Resources\Receipts\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Receipts\ReceiptResource;
use Filament\Actions\DeleteAction;

class EditReceipt extends EditRecord
{
    protected static string $resource = ReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
