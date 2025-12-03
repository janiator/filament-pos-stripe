<?php

namespace App\Filament\Resources\PaymentMethods\Pages;

use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentMethod extends CreateRecord
{
    protected static string $resource = PaymentMethodResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-set store_id from tenant if not provided
        if (empty($data['store_id'])) {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant) {
                $data['store_id'] = $tenant->id;
            }
        }

        // Auto-fill SAF-T codes based on payment method code and provider_method if not provided
        if (!empty($data['code']) && empty($data['saf_t_payment_code'])) {
            $providerMethod = $data['provider_method'] ?? null;
            $data['saf_t_payment_code'] = \App\Services\SafTCodeMapper::mapPaymentMethodToCode($data['code'], $providerMethod);
        }

        if (!empty($data['code']) && empty($data['saf_t_event_code'])) {
            $providerMethod = $data['provider_method'] ?? null;
            $data['saf_t_event_code'] = \App\Services\SafTCodeMapper::mapPaymentMethodToEventCode($data['code'], $providerMethod);
        }

        return $data;
    }
}
