<?php

namespace App\Filament\Resources\GiftCards\Pages;

use App\Filament\Resources\GiftCards\GiftCardResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditGiftCard extends EditRecord
{
    protected static string $resource = GiftCardResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert øre to kroner for display
        if (isset($data['initial_amount'])) {
            $data['initial_amount_kroner'] = $data['initial_amount'] / 100;
        }
        if (isset($data['balance'])) {
            $data['balance_kroner'] = $data['balance'] / 100;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
