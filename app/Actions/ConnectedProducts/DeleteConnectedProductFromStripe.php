<?php

namespace App\Actions\ConnectedProducts;

use App\Models\ConnectedProduct;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class DeleteConnectedProductFromStripe
{
    /**
     * Archive a product in Stripe (deactivate it)
     * 
     * Products that have been used cannot be deleted in Stripe, so we always archive them.
     * Archiving sets active=false, which can be reactivated later if needed.
     * 
     * @param ConnectedProduct $product
     * @return void
     */
    public function __invoke(ConnectedProduct $product): void
    {
        if (! $product->stripe_product_id || ! $product->stripe_account_id) {
            Log::warning('Cannot archive product in Stripe: missing Stripe IDs', [
                'product_id' => $product->id,
                'stripe_product_id' => $product->stripe_product_id,
                'stripe_account_id' => $product->stripe_account_id,
            ]);
            return;
        }

        $store = Store::where('stripe_account_id', $product->stripe_account_id)->first();
        if (! $store || ! $store->hasStripeAccount()) {
            Log::warning('Cannot archive product in Stripe: store not found or invalid', [
                'product_id' => $product->id,
                'stripe_account_id' => $product->stripe_account_id,
            ]);
            return;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (! $secret) {
            Log::warning('Cannot archive product in Stripe: Stripe secret not configured');
            return;
        }

        $stripe = new StripeClient($secret);

        try {
            // Archive the product by setting active=false
            // Products that have been used cannot be deleted, only archived
            // This can be reactivated later if needed
            $stripe->products->update(
                $product->stripe_product_id,
                ['active' => false],
                ['stripe_account' => $product->stripe_account_id]
            );
            
            Log::info('Archived product in Stripe', [
                'product_id' => $product->id,
                'stripe_product_id' => $product->stripe_product_id,
                'stripe_account_id' => $product->stripe_account_id,
                'product_name' => $product->name,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to archive product in Stripe', [
                'product_id' => $product->id,
                'stripe_product_id' => $product->stripe_product_id,
                'stripe_account_id' => $product->stripe_account_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            // Don't throw - allow local deletion to proceed even if Stripe archiving fails
            report($e);
        }
    }
}

