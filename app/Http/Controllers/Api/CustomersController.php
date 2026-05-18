<?php

namespace App\Http\Controllers\Api;

use App\Models\ConnectedCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomersController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $query = ConnectedCustomer::query()
            ->where('stripe_account_id', $store->stripe_account_id);

        if (! $request->boolean('include_archived')) {
            $query->notArchived();
        }

        if ($request->has('search') && ! empty($request->get('search'))) {
            $search = trim($request->get('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        $perPage = min($request->get('per_page', 15), 100);
        $page = max(1, (int) $request->get('page', 0) + 1);

        $paginatedCustomers = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $customers = $paginatedCustomers->getCollection()
            ->map(fn (ConnectedCustomer $customer): array => $this->transformCustomerForApi($customer));

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

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:255',
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

        if (isset($validated['customer_address'])) {
            $validated['address'] = $validated['customer_address'];
            unset($validated['customer_address']);
        }

        $customer = ConnectedCustomer::create($validated);

        if ($store->hasStripeAccount()) {
            try {
                $createAction = new \App\Actions\ConnectedCustomers\CreateConnectedCustomerInStripe;
                $stripeCustomerId = $createAction($store, [
                    'name' => $validated['name'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                ]);

                if ($stripeCustomerId) {
                    $customer->stripe_customer_id = $stripeCustomerId;
                    $customer->saveQuietly();

                    try {
                        $updateAction = new \App\Actions\ConnectedCustomers\UpdateConnectedCustomerToStripe;
                        $updateAction($customer);
                    } catch (\Throwable $e) {
                        \Log::warning('Failed to sync customer data to Stripe after setting stripe_customer_id', [
                            'customer_id' => $customer->id,
                            'stripe_customer_id' => $stripeCustomerId,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $customer->refresh();
                } else {
                    \Log::warning('Failed to create customer in Stripe after local creation', [
                        'customer_id' => $customer->id,
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::error('Error creating Stripe customer after local creation', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return response()->json($this->transformCustomerForApi($customer), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $customer = ConnectedCustomer::where('id', $id)
            ->where('stripe_account_id', $store->stripe_account_id)
            ->with(['subscriptions'])
            ->firstOrFail();

        return response()->json($this->transformCustomerForApi($customer));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $customer = ConnectedCustomer::where('id', $id)
            ->where('stripe_account_id', $store->stripe_account_id)
            ->firstOrFail();

        if ($customer->isArchived()) {
            return response()->json([
                'error' => 'Cannot update an archived customer.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:255',
            'profile_image_url' => 'nullable|url|max:500',
            'customer_address' => 'nullable|array',
            'customer_address.line1' => 'nullable|string|max:255',
            'customer_address.line2' => 'nullable|string|max:255',
            'customer_address.city' => 'nullable|string|max:255',
            'customer_address.state' => 'nullable|string|max:255',
            'customer_address.postal_code' => 'nullable|string|max:255',
            'customer_address.country' => 'nullable|string|size:2',
        ]);

        if (isset($validated['customer_address'])) {
            $validated['address'] = $validated['customer_address'];
            unset($validated['customer_address']);
        }

        $customer->update($validated);

        return response()->json($this->transformCustomerForApi($customer));
    }

    /**
     * Archive the customer (preserves purchase history and Stripe mapping).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $customer = ConnectedCustomer::where('id', $id)
            ->where('stripe_account_id', $store->stripe_account_id)
            ->firstOrFail();

        $customer->archive();

        return response()->json([
            'message' => 'Customer archived.',
            'customer' => $this->transformCustomerForApi($customer->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformCustomerForApi(ConnectedCustomer $customer): array
    {
        $customerData = $customer->makeHidden(['model', 'model_id', 'model_uuid'])->toArray();

        if (isset($customerData['address'])) {
            $customerData['customer_address'] = $customerData['address'];
            unset($customerData['address']);
        }

        $customerData['archived_at'] = $customer->archived_at
            ? $this->formatDateTimeOslo($customer->archived_at)
            : null;

        return $customerData;
    }
}
