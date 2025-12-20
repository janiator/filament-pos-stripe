<?php

namespace App\Filament\Resources\GiftCards\Pages;

use App\Filament\Resources\GiftCards\GiftCardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGiftCard extends CreateRecord
{
    protected static string $resource = GiftCardResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set store_id from tenant if not set
        if (!isset($data['store_id'])) {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant) {
                $data['store_id'] = $tenant->id;
            }
        }

        // Set default expiration if purchased_at is set
        if (isset($data['purchased_at']) && !isset($data['expires_at'])) {
            $storeId = $data['store_id'] ?? null;
            $expirationDays = 365; // Default 1 year
            
            if ($storeId) {
                $store = \App\Models\Store::find($storeId);
                if ($store) {
                    $settings = \App\Models\Setting::getForStore($store->id);
                    $expirationDays = $settings->gift_card_expiration_days ?? 365;
                }
            }
            
            $purchasedAt = \Carbon\Carbon::parse($data['purchased_at']);
            $data['expires_at'] = $purchasedAt->copy()->addDays($expirationDays);
        }

        return $data;
    }
}
