<?php

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Resources\Coupons\CouponResource;
use App\Models\Store;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;

    /**
     * Set stripe_account_id from the selected store so the NOT NULL column is satisfied.
     * Required for Stripe Connect (coupons are per connected account).
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
