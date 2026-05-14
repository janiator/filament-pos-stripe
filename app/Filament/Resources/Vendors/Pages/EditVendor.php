<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Vendors\VendorResource;
use Filament\Actions\DeleteAction;

class EditVendor extends EditRecord
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
