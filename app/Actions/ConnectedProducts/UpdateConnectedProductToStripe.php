<?php

namespace App\Actions\ConnectedProducts;

use App\Models\ConnectedProduct;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;

class UpdateConnectedProductToStripe
{
    public function __invoke(ConnectedProduct $product): void
    {
        if (! $product->stripe_product_id || ! $product->stripe_account_id) {
            return;
        }

        $store = Store::where('stripe_account_id', $product->stripe_account_id)->first();
        if (! $store || ! $store->hasStripeAccount()) {
            return;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (! $secret) {
            return;
        }

        $stripe = new StripeClient($secret);

        try {
            $updateData = [];

            // Add all syncable fields (listener already checked for changes)
            if ($product->name !== null) {
                $updateData['name'] = $product->name;
            }

            if ($product->description !== null) {
                $updateData['description'] = $product->description;
            }

            $updateData['active'] = $product->active ?? true;

            if ($product->images !== null) {
                $updateData['images'] = $product->images ?? [];
            }

            if ($product->metadata !== null) {
                $updateData['metadata'] = $product->metadata ?? [];
            }

            if ($product->url !== null) {
                $updateData['url'] = $product->url;
            }

            if (! empty($updateData)) {
                $stripe->products->update(
                    $product->stripe_product_id,
                    $updateData,
                    ['stripe_account' => $product->stripe_account_id]
                );
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}

