<?php

namespace App\Actions\ConnectedCustomers;

use App\Models\Store;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class CreateConnectedCustomerInStripe
{
    public function __invoke(Store $store, array $customerData): ?string
    {
        if (!$store->hasStripeAccount()) {
            Log::warning('Cannot create customer in Stripe: store not connected', [
                'store_id' => $store->id,
            ]);
            return null;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (!$secret) {
            Log::warning('Cannot create customer in Stripe: Stripe secret not configured');
            return null;
        }

        $stripe = new StripeClient($secret);

        try {
            $createData = [];

            // Add optional fields if they exist
            if (isset($customerData['name']) && $customerData['name']) {
                $createData['name'] = $customerData['name'];
            }

            if (isset($customerData['email']) && $customerData['email']) {
                $createData['email'] = $customerData['email'];
            }

            if (isset($customerData['phone']) && $customerData['phone']) {
                $createData['phone'] = $customerData['phone'];
            }

            if (isset($customerData['address']) && is_array($customerData['address']) && !empty($customerData['address'])) {
                $createData['address'] = $customerData['address'];
            }

            // Create customer in Stripe
            $stripeCustomer = $stripe->customers->create(
                $createData,
                ['stripe_account' => $store->stripe_account_id]
            );

            Log::info('Created customer in Stripe', [
                'stripe_customer_id' => $stripeCustomer->id,
                'stripe_account_id' => $store->stripe_account_id,
                'customer_name' => $customerData['name'] ?? null,
            ]);

            return $stripeCustomer->id;
        } catch (Throwable $e) {
            Log::error('Failed to create customer in Stripe', [
                'stripe_account_id' => $store->stripe_account_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            report($e);
            return null;
        }
    }
}

