<?php

namespace App\Filament\Resources\ConnectedProducts\Pages;

use App\Actions\ConnectedProducts\CreateConnectedProductInStripe;
use App\Filament\Resources\ConnectedProducts\ConnectedProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConnectedProduct extends CreateRecord
{
    protected static string $resource = ConnectedProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Handle compare_at_price_decimal conversion
        if (isset($data['compare_at_price_decimal'])) {
            if ($data['compare_at_price_decimal'] !== null && $data['compare_at_price_decimal'] !== '') {
                $data['compare_at_price_amount'] = (int) round($data['compare_at_price_decimal'] * 100);
            } else {
                $data['compare_at_price_amount'] = null;
            }
            unset($data['compare_at_price_decimal']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $product = $this->record;

        // Create product in Stripe if not already created
        if (!$product->stripe_product_id && $product->stripe_account_id) {
            $createAction = new CreateConnectedProductInStripe();
            $stripeProductId = $createAction($product);

            if ($stripeProductId) {
                $product->stripe_product_id = $stripeProductId;
                $product->saveQuietly(); // Save without triggering events
            }
        }

        // Sync price if set (only for single products, variable products use variant prices)
        if (!$product->isVariable() && $product->price && $product->stripe_product_id && $product->stripe_account_id) {
            $syncPriceAction = new \App\Actions\ConnectedPrices\SyncProductPrice();
            $syncPriceAction($product);
        }
    }
}
