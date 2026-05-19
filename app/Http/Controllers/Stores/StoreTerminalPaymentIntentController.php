<?php

namespace App\Http\Controllers\Stores;

use App\Models\ConnectedCustomer;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Stripe\StripeClient;
use Throwable;

class StoreTerminalPaymentIntentController extends Controller
{
    public function __invoke(Store $store, Request $request): JsonResponse
    {
        // TODO: Authorize the caller (e.g. Sanctum / JWT / your own guard)

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],  // in minor units
            'currency' => ['nullable', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! $store->hasStripeAccount()) {
            return response()->json([
                'message' => 'This store is not connected to Stripe.',
            ], 422);
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');

        if (! $secret) {
            return response()->json([
                'message' => 'Stripe secret key is not configured.',
            ], 500);
        }

        $currency = strtolower(
            $validated['currency']
            ?? ($store->currency ?? config('cashier.currency', 'usd'))
        );

        $stripe = new StripeClient($secret);

        try {
            $params = [
                'amount' => $validated['amount'],
                'currency' => $currency,
                'payment_method_types' => ['card_present'],
                'capture_method' => 'automatic', // Terminal flow: create → collect → capture
            ];

            if (! empty($validated['description'])) {
                $params['description'] = $validated['description'];
            }

            if (! empty($validated['metadata'])) {
                $params['metadata'] = $validated['metadata'];
            }

            if (! empty($validated['customer_id'])) {
                $customer = ConnectedCustomer::query()
                    ->where('id', (int) $validated['customer_id'])
                    ->where('stripe_account_id', $store->stripe_account_id)
                    ->notArchived()
                    ->first();

                if ($customer?->stripe_customer_id) {
                    $params['customer'] = $customer->stripe_customer_id;
                }
            }

            // Create PaymentIntent on the CONNECTED ACCOUNT
            $intent = $stripe->paymentIntents->create(
                $params,
                ['stripe_account' => $store->stripe_account_id]
            );

            return response()->json($intent, 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Failed to create payment intent.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
