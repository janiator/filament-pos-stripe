<?php

namespace App\Filament\Resources\Receipts\Pages;

use App\Filament\Resources\Receipts\ReceiptResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReceipt extends CreateRecord
{
    protected static string $resource = ReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get current tenant/store
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant && $tenant->slug !== 'visivo-admin') {
            $data['store_id'] = $tenant->id;
        }

        // Ensure store_id is set
        if (!isset($data['store_id'])) {
            $data['store_id'] = $tenant?->id ?? auth()->user()?->currentStore()?->id;
        }

        // Set default user if not provided
        if (empty($data['user_id'])) {
            $data['user_id'] = auth()->id();
        }

        return $data;
    }
}
