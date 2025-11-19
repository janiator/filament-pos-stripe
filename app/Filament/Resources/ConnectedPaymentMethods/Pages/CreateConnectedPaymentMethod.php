<?php

namespace App\Filament\Resources\ConnectedPaymentMethods\Pages;

use App\Filament\Resources\ConnectedPaymentMethods\ConnectedPaymentMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConnectedPaymentMethod extends CreateRecord
{
    protected static string $resource = ConnectedPaymentMethodResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Payment methods are typically created via Stripe.js or Payment Intents
        // This form is mainly for viewing/managing existing payment methods
        throw new \Exception('Payment methods should be created through Stripe.js or Payment Intents, not manually.');
    }
}
