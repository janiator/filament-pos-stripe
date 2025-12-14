<?php

namespace App\Http\Controllers\Api;

use App\Models\ConnectedCustomer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomersController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $perPage = min($request->get('per_page', 15), 100); // Max 100 per page
        // Convert zero-indexed page from FlutterFlow to 1-indexed for Laravel
        $page = max(1, (int) $request->get('page', 0) + 1);
        
        $paginatedCustomers = ConnectedCustomer::where('stripe_account_id', $store->stripe_account_id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Transform customers to exclude internal mapping fields and rename address
        $customers = $paginatedCustomers->getCollection()->map(function ($customer) {
            $customerData = $customer->makeHidden(['model', 'model_id', 'model_uuid'])->toArray();
            if (isset($customerData['address'])) {
                $customerData['customer_address'] = $customerData['address'];
                unset($customerData['address']);
            }
            return $customerData;
        });

        // Convert back to zero-indexed for FlutterFlow
        return response()->json([
            'customers' => $customers,
            'current_page' => $paginatedCustomers->currentPage() - 1,
            'last_page' => $paginatedCustomers->lastPage() - 1,
            'per_page' => $paginatedCustomers->perPage(),
            'total' => $paginatedCustomers->total(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'profile_image_url' => 'nullable|url|max:500',
            'customer_address' => 'nullable|array',
            'customer_address.line1' => 'nullable|string|max:255',
            'customer_address.line2' => 'nullable|string|max:255',
            'customer_address.city' => 'nullable|string|max:255',
            'customer_address.state' => 'nullable|string|max:255',
            'customer_address.postal_code' => 'nullable|string|max:255',
            'customer_address.country' => 'nullable|string|size:2',
        ]);

        $validated['stripe_account_id'] = $store->stripe_account_id;

        // Map customer_address to address for database storage
        if (isset($validated['customer_address'])) {
            $validated['address'] = $validated['customer_address'];
            unset($validated['customer_address']);
        }

        // Create local customer first
        $customer = ConnectedCustomer::create($validated);

        // Create Stripe customer after local customer is created
        if ($store->hasStripeAccount()) {
            try {
                $createAction = new \App\Actions\ConnectedCustomers\CreateConnectedCustomerInStripe();
                $stripeCustomerId = $createAction($store, [
                    'name' => $validated['name'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                ]);
                
                if ($stripeCustomerId) {
                    // Update with stripe_customer_id and trigger a sync to ensure all data is synced
                    // Use updateQuietly first to set the ID, then save to trigger sync
                    $customer->stripe_customer_id = $stripeCustomerId;
                    $customer->saveQuietly(); // Set the ID without triggering events
                    
                    // Now trigger a sync to ensure all customer data is synced to Stripe
                    // This ensures that if there were any differences, they get synced
                    try {
                        $updateAction = new \App\Actions\ConnectedCustomers\UpdateConnectedCustomerToStripe();
                        $updateAction($customer);
                    } catch (\Throwable $e) {
                        // Log but don't fail - the customer was created in Stripe with the data already
                        \Log::warning('Failed to sync customer data to Stripe after setting stripe_customer_id', [
                            'customer_id' => $customer->id,
                            'stripe_customer_id' => $stripeCustomerId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    
                    $customer->refresh();
                } else {
                    // If Stripe creation fails, we still have the local customer
                    // Log the error but don't fail the request
                    \Log::warning('Failed to create customer in Stripe after local creation', [
                        'customer_id' => $customer->id,
                    ]);
                }
            } catch (\Throwable $e) {
                // If Stripe creation fails, we still have the local customer
                // Log the error but don't fail the request
                \Log::error('Error creating Stripe customer after local creation', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Transform response to rename address to customer_address
        $customerData = $customer->makeHidden(['model', 'model_id', 'model_uuid'])->toArray();
        if (isset($customerData['address'])) {
            $customerData['customer_address'] = $customerData['address'];
            unset($customerData['address']);
        }

        return response()->json($customerData, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $customer = ConnectedCustomer::where('id', $id)
            ->where('stripe_account_id', $store->stripe_account_id)
            ->with(['subscriptions'])
            ->firstOrFail();

        // Transform response to rename address to customer_address
        $customerData = $customer->makeHidden(['model', 'model_id', 'model_uuid'])->toArray();
        if (isset($customerData['address'])) {
            $customerData['customer_address'] = $customerData['address'];
            unset($customerData['address']);
        }

        return response()->json($customerData);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $customer = ConnectedCustomer::where('id', $id)
            ->where('stripe_account_id', $store->stripe_account_id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'profile_image_url' => 'nullable|url|max:500',
            'customer_address' => 'nullable|array',
            'customer_address.line1' => 'nullable|string|max:255',
            'customer_address.line2' => 'nullable|string|max:255',
            'customer_address.city' => 'nullable|string|max:255',
            'customer_address.state' => 'nullable|string|max:255',
            'customer_address.postal_code' => 'nullable|string|max:255',
            'customer_address.country' => 'nullable|string|size:2',
        ]);

        // Map customer_address to address for database storage
        if (isset($validated['customer_address'])) {
            $validated['address'] = $validated['customer_address'];
            unset($validated['customer_address']);
        }

        $customer->update($validated);

        // Transform response to rename address to customer_address
        $customerData = $customer->makeHidden(['model', 'model_id', 'model_uuid'])->toArray();
        if (isset($customerData['address'])) {
            $customerData['customer_address'] = $customerData['address'];
            unset($customerData['address']);
        }

        return response()->json($customerData);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $customer = ConnectedCustomer::where('id', $id)
            ->where('stripe_account_id', $store->stripe_account_id)
            ->firstOrFail();

        $customer->delete();

        return response()->json(null, 204);
    }
}
