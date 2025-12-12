<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Vendors\VendorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVendor extends CreateRecord
{
    protected static string $resource = VendorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-set store_id and stripe_account_id from tenant
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant && $tenant->slug !== 'visivo-admin') {
            $data['store_id'] = $tenant->id;
            $data['stripe_account_id'] = $tenant->stripe_account_id;
        }

        // Ensure stripe_account_id is set
        if (empty($data['stripe_account_id'])) {
            throw new \Exception('Cannot create vendor: stripe_account_id is required. Please ensure you are creating the vendor within a store context.');
        }

        return $data;
    }
}
