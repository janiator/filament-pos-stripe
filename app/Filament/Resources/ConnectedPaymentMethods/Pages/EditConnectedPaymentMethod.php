<?php

namespace App\Filament\Resources\ConnectedPaymentMethods\Pages;

use App\Filament\Resources\ConnectedPaymentMethods\ConnectedPaymentMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConnectedPaymentMethod extends EditRecord
{
    protected static string $resource = ConnectedPaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // If this is set as default, unset other default payment methods for this customer
        if ($this->record->is_default) {
            \App\Models\ConnectedPaymentMethod::where('stripe_customer_id', $this->record->stripe_customer_id)
                ->where('stripe_account_id', $this->record->stripe_account_id)
                ->where('id', '!=', $this->record->id)
                ->update(['is_default' => false]);
        }
    }
}
