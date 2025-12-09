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
        $paginatedCustomers = ConnectedCustomer::where('stripe_account_id', $store->stripe_account_id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Transform customers to exclude internal mapping fields and rename address
        $customers = $paginatedCustomers->getCollection()->map(function ($customer) {
            $customerData = $customer->makeHidden(['model', 'model_id', 'model_uuid'])->toArray();
            if (isset($customerData['address'])) {
                $customerData['customer_address'] = $customerData['address'];
                unset($customerData['address']);
            }
            return $customerData;
        });

        return response()->json([
            'customers' => $customers,
            'current_page' => $paginatedCustomers->currentPage(),
            'last_page' => $paginatedCustomers->lastPage(),
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
            'stripe_customer_id' => 'required|string',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'profile_image_url' => 'nullable|url|max:500',
            'address' => 'nullable|array',
            'address.line1' => 'nullable|string|max:255',
            'address.line2' => 'nullable|string|max:255',
            'address.city' => 'nullable|string|max:255',
            'address.state' => 'nullable|string|max:255',
            'address.postal_code' => 'nullable|string|max:255',
            'address.country' => 'nullable|string|size:2',
            'customer_address' => 'nullable|array',
            'customer_address.line1' => 'nullable|string|max:255',
            'customer_address.line2' => 'nullable|string|max:255',
            'customer_address.city' => 'nullable|string|max:255',
            'customer_address.state' => 'nullable|string|max:255',
            'customer_address.postal_code' => 'nullable|string|max:255',
            'customer_address.country' => 'nullable|string|size:2',
            'model' => 'nullable|string',
            'model_id' => 'nullable|integer',
            'model_uuid' => 'nullable|uuid',
        ]);

        $validated['stripe_account_id'] = $store->stripe_account_id;

        // Handle customer_address in request (map to address for database)
        if (isset($validated['customer_address'])) {
            $validated['address'] = $validated['customer_address'];
            unset($validated['customer_address']);
        }

        $customer = ConnectedCustomer::create($validated);

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
            'address' => 'nullable|array',
            'address.line1' => 'nullable|string|max:255',
            'address.line2' => 'nullable|string|max:255',
            'address.city' => 'nullable|string|max:255',
            'address.state' => 'nullable|string|max:255',
            'address.postal_code' => 'nullable|string|max:255',
            'address.country' => 'nullable|string|size:2',
            'customer_address' => 'nullable|array',
            'customer_address.line1' => 'nullable|string|max:255',
            'customer_address.line2' => 'nullable|string|max:255',
            'customer_address.city' => 'nullable|string|max:255',
            'customer_address.state' => 'nullable|string|max:255',
            'customer_address.postal_code' => 'nullable|string|max:255',
            'customer_address.country' => 'nullable|string|size:2',
            'model' => 'nullable|string',
            'model_id' => 'nullable|integer',
            'model_uuid' => 'nullable|uuid',
        ]);

        // Handle customer_address in request (map to address for database)
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
