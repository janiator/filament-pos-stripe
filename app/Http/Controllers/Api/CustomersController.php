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

        $customers = ConnectedCustomer::where('stripe_account_id', $store->stripe_account_id)
            ->with(['store'])
            ->paginate($request->get('per_page', 15));

        return response()->json($customers);
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
            'address' => 'nullable|array',
            'address.line1' => 'nullable|string|max:255',
            'address.line2' => 'nullable|string|max:255',
            'address.city' => 'nullable|string|max:255',
            'address.state' => 'nullable|string|max:255',
            'address.postal_code' => 'nullable|string|max:255',
            'address.country' => 'nullable|string|size:2',
            'model' => 'nullable|string',
            'model_id' => 'nullable|integer',
            'model_uuid' => 'nullable|uuid',
        ]);

        $validated['stripe_account_id'] = $store->stripe_account_id;

        $customer = ConnectedCustomer::create($validated);

        return response()->json($customer, 201);
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
            ->with(['store', 'subscriptions'])
            ->firstOrFail();

        return response()->json($customer);
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
            'address' => 'nullable|array',
            'address.line1' => 'nullable|string|max:255',
            'address.line2' => 'nullable|string|max:255',
            'address.city' => 'nullable|string|max:255',
            'address.state' => 'nullable|string|max:255',
            'address.postal_code' => 'nullable|string|max:255',
            'address.country' => 'nullable|string|size:2',
            'model' => 'nullable|string',
            'model_id' => 'nullable|integer',
            'model_uuid' => 'nullable|uuid',
        ]);

        $customer->update($validated);

        return response()->json($customer);
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
