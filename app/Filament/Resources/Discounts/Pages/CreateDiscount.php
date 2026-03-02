<?php

namespace App\Filament\Resources\Discounts\Pages;

use App\Filament\Resources\Discounts\DiscountResource;
use App\Models\Store;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscount extends CreateRecord
{
    protected static string $resource = DiscountResource::class;

    /**
     * Set stripe_account_id from the selected store so the NOT NULL column is satisfied.
     * Required for Stripe Connect (discounts/promotion codes are per connected account).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $storeId = $data['store_id'] ?? null;
        if ($storeId) {
            $store = Store::find($storeId);
            if ($store && $store->stripe_account_id) {
                $data['stripe_account_id'] = $store->stripe_account_id;
            }
        }

        return $data;
    }
}
