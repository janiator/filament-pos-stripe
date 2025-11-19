<?php

namespace App\Filament\Resources\ConnectedPaymentLinks\Pages;

use App\Filament\Resources\ConnectedPaymentLinks\ConnectedPaymentLinkResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConnectedPaymentLink extends EditRecord
{
    protected static string $resource = ConnectedPaymentLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    // Payment links can be updated in Stripe, but we'll keep it read-only for now
    // You can add update logic here if needed
}
