<?php

namespace App\Filament\Resources\ConnectedProducts\Pages;

use App\Actions\ConnectedProducts\CreateConnectedProductInStripe;
use App\Filament\Resources\ConnectedProducts\ConnectedProductResource;
use App\Models\Store;
use Filament\Resources\Pages\CreateRecord;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;

class CreateConnectedProduct extends CreateRecord
{
    protected static string $resource = ConnectedProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set store from current tenant
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant && $tenant->slug !== 'visivo-admin' && $tenant->stripe_account_id) {
            $data['stripe_account_id'] = $tenant->stripe_account_id;
        }

        // Ensure stripe_account_id is set
        if (empty($data['stripe_account_id'])) {
            $data['stripe_account_id'] = $tenant?->stripe_account_id ?? auth()->user()?->currentStore()?->stripe_account_id;
        }

        // Handle compare_at_price_decimal conversion
        if (isset($data['compare_at_price_decimal'])) {
            if ($data['compare_at_price_decimal'] !== null && $data['compare_at_price_decimal'] !== '') {
                $data['compare_at_price_amount'] = (int) round($data['compare_at_price_decimal'] * 100);
            } else {
                $data['compare_at_price_amount'] = null;
            }
            unset($data['compare_at_price_decimal']);
        }

        // Create product in Stripe BEFORE saving to database
        // This is required because stripe_product_id has a NOT NULL constraint
        if (empty($data['stripe_product_id']) && !empty($data['stripe_account_id'])) {
            $stripeProductId = $this->createStripeProductFromData($data);
            if ($stripeProductId) {
                $data['stripe_product_id'] = $stripeProductId;
            } else {
                // If Stripe creation fails, we can't proceed
                // The form validation should prevent this, but handle gracefully
                throw new \Exception('Failed to create product in Stripe. Please check your Stripe account configuration.');
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $product = $this->record;

        // Sync price if set (only for single products, variable products use variant prices)
        if (!$product->isVariable() && $product->price && $product->stripe_product_id && $product->stripe_account_id) {
            $syncPriceAction = new \App\Actions\ConnectedPrices\SyncProductPrice();
            $syncPriceAction($product);
        }
    }

    /**
     * Create a Stripe product from form data before the model is saved
     */
    protected function createStripeProductFromData(array $data): ?string
    {
        if (empty($data['stripe_account_id'])) {
            Log::warning('Cannot create product in Stripe: missing stripe_account_id');
            return null;
        }

        $store = Store::where('stripe_account_id', $data['stripe_account_id'])->first();
        if (! $store || ! $store->hasStripeAccount()) {
            Log::warning('Cannot create product in Stripe: store not found or invalid', [
                'stripe_account_id' => $data['stripe_account_id'],
            ]);
            return null;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (! $secret) {
            Log::warning('Cannot create product in Stripe: Stripe secret not configured');
            return null;
        }

        $stripe = new StripeClient($secret);

        try {
            $createData = [
                'name' => $data['name'] ?? 'Untitled Product',
                'type' => $data['type'] ?? 'service',
            ];

            // Add optional fields if they exist
            if (!empty($data['description'])) {
                $createData['description'] = $data['description'];
            }

            if (isset($data['active'])) {
                $createData['active'] = (bool) $data['active'];
            }

            if (!empty($data['url'])) {
                $createData['url'] = $data['url'];
            }

            if (isset($data['shippable'])) {
                $createData['shippable'] = (bool) $data['shippable'];
            }

            if (!empty($data['statement_descriptor'])) {
                $createData['statement_descriptor'] = $data['statement_descriptor'];
            }

            if (!empty($data['tax_code'])) {
                $createData['tax_code'] = $data['tax_code'];
            }


            if (!empty($data['package_dimensions']) && is_array($data['package_dimensions'])) {
                $createData['package_dimensions'] = $data['package_dimensions'];
            }

            if (!empty($data['product_meta']) && is_array($data['product_meta'])) {
                // Ensure all metadata values are strings
                $metadata = [];
                foreach ($data['product_meta'] as $key => $value) {
                    if (!is_string($key) || 
                        str_contains($key, "\0") || 
                        str_contains($key, '*') || 
                        str_contains($key, '_opts') ||
                        str_starts_with($key, '_')) {
                        continue;
                    }
                    
                    if (is_string($value)) {
                        $metadata[$key] = $value;
                    } elseif (is_scalar($value)) {
                        $metadata[$key] = (string) $value;
                    } elseif (!is_null($value)) {
                        $metadata[$key] = json_encode($value);
                    }
                }
                
                if (!empty($metadata)) {
                    $createData['metadata'] = $metadata;
                }
            }

            // Create product in Stripe
            $stripeProduct = $stripe->products->create(
                $createData,
                ['stripe_account' => $data['stripe_account_id']]
            );

            Log::info('Created product in Stripe', [
                'stripe_product_id' => $stripeProduct->id,
                'stripe_account_id' => $data['stripe_account_id'],
                'product_name' => $data['name'] ?? 'Untitled Product',
            ]);

            return $stripeProduct->id;
        } catch (\Throwable $e) {
            Log::error('Failed to create product in Stripe', [
                'stripe_account_id' => $data['stripe_account_id'],
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            report($e);
            return null;
        }
    }
}
