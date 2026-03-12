<?php

namespace App\Filament\Resources\PaymentMethods\Pages;

use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPaymentMethod extends EditRecord
{
    protected static string $resource = PaymentMethodResource::class;

    /** @var array<int> */
    protected array $posDeviceIdsToSync = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->posDeviceIdsToSync = $data['posDevices'] ?? [];
        unset($data['posDevices']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->posDevices()->sync($this->posDeviceIdsToSync ?? []);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
