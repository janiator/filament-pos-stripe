<?php

namespace App\Filament\Resources\ConnectedTransfers\Pages;

use App\Filament\Resources\ConnectedTransfers\ConnectedTransferResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConnectedTransfer extends EditRecord
{
    protected static string $resource = ConnectedTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    // Transfers are immutable in Stripe - disable editing
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Prevent saving - transfers are read-only
        throw new \Exception('Transfers cannot be edited. They are immutable in Stripe.');
    }
}
