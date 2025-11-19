<?php

namespace App\Filament\Resources\ConnectedCharges\Pages;

use App\Filament\Resources\ConnectedCharges\ConnectedChargeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConnectedCharge extends EditRecord
{
    protected static string $resource = ConnectedChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    // Charges are immutable in Stripe - disable editing
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Prevent saving - charges are read-only
        throw new \Exception('Charges cannot be edited. They are immutable in Stripe.');
    }
}
