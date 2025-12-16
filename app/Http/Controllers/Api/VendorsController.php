<?php

namespace App\Http\Controllers\Api;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VendorsController extends BaseApiController
{
    /**
     * Display a listing of vendors
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $store = $this->getTenantStore($request);
            
            if (!$store) {
                return response()->json(['error' => 'Store not found'], 404);
            }

            $this->authorizeTenant($request, $store);

            // Build query
            $query = Vendor::where('stripe_account_id', $store->stripe_account_id);

            // Filter by active status if provided
            if ($request->has('active')) {
                $query->where('active', filter_var($request->get('active'), FILTER_VALIDATE_BOOLEAN));
            }

            // Filter by search term if provided
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('description', 'ilike', "%{$search}%")
                      ->orWhere('contact_email', 'ilike', "%{$search}%")
                      ->orWhere('contact_phone', 'ilike', "%{$search}%");
                });
            }

            // Get paginated results
            $perPage = min($request->get('per_page', 50), 100); // Max 100 per page
            $vendors = $query->withCount('products')
                ->orderBy('name')
                ->paginate($perPage);

            // Transform vendors
            $transformedVendors = $vendors->getCollection()->map(function ($vendor) {
                return [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'description' => $vendor->description,
                    'contact_email' => $vendor->contact_email,
                    'contact_phone' => $vendor->contact_phone,
                    'active' => $vendor->active,
                    'commission_percent' => $vendor->commission_percent,
                    'products_count' => $vendor->products_count,
                    'metadata' => $vendor->metadata,
                    'created_at' => $this->formatDateTimeOslo($vendor->created_at),
                    'updated_at' => $this->formatDateTimeOslo($vendor->updated_at),
                ];
            });

            return response()->json([
                'vendors' => $transformedVendors,
                'meta' => [
                    'current_page' => $vendors->currentPage(),
                    'last_page' => $vendors->lastPage(),
                    'per_page' => $vendors->perPage(),
                    'total' => $vendors->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error in VendorsController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Display the specified vendor
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $vendor = Vendor::where('stripe_account_id', $store->stripe_account_id)
            ->withCount('products')
            ->findOrFail($id);

        return response()->json([
            'vendor' => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'description' => $vendor->description,
                'contact_email' => $vendor->contact_email,
                'contact_phone' => $vendor->contact_phone,
                'active' => $vendor->active,
                'commission_percent' => $vendor->commission_percent,
                'products_count' => $vendor->products_count,
                'metadata' => $vendor->metadata,
                'created_at' => $this->formatDateTimeOslo($vendor->created_at),
                'updated_at' => $this->formatDateTimeOslo($vendor->updated_at),
            ],
        ]);
    }

    /**
     * Store a newly created vendor
     */
    public function store(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'metadata' => 'nullable|array',
        ]);

        try {
            $vendor = new Vendor();
            $vendor->store_id = $store->id;
            $vendor->stripe_account_id = $store->stripe_account_id;
            $vendor->name = $validated['name'];
            $vendor->description = $validated['description'] ?? null;
            $vendor->contact_email = $validated['contact_email'] ?? null;
            $vendor->contact_phone = $validated['contact_phone'] ?? null;
            $vendor->active = $validated['active'] ?? true;
            $vendor->commission_percent = $validated['commission_percent'] ?? null;
            $vendor->metadata = $validated['metadata'] ?? null;
            $vendor->save();

            return response()->json([
                'vendor' => [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'description' => $vendor->description,
                    'contact_email' => $vendor->contact_email,
                    'contact_phone' => $vendor->contact_phone,
                    'active' => $vendor->active,
                    'commission_percent' => $vendor->commission_percent,
                    'products_count' => 0,
                    'metadata' => $vendor->metadata,
                    'created_at' => $this->formatDateTimeOslo($vendor->created_at),
                    'updated_at' => $this->formatDateTimeOslo($vendor->updated_at),
                ],
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('Error in VendorsController@store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to create vendor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified vendor
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $vendor = Vendor::where('stripe_account_id', $store->stripe_account_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'metadata' => 'nullable|array',
        ]);

        try {
            if (isset($validated['name'])) {
                $vendor->name = $validated['name'];
            }
            if (isset($validated['description'])) {
                $vendor->description = $validated['description'];
            }
            if (isset($validated['contact_email'])) {
                $vendor->contact_email = $validated['contact_email'];
            }
            if (isset($validated['contact_phone'])) {
                $vendor->contact_phone = $validated['contact_phone'];
            }
            if (isset($validated['active'])) {
                $vendor->active = $validated['active'];
            }
            if (isset($validated['commission_percent'])) {
                $vendor->commission_percent = $validated['commission_percent'];
            }
            if (isset($validated['metadata'])) {
                $vendor->metadata = $validated['metadata'];
            }

            $vendor->save();

            return response()->json([
                'vendor' => [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'description' => $vendor->description,
                    'contact_email' => $vendor->contact_email,
                    'contact_phone' => $vendor->contact_phone,
                    'active' => $vendor->active,
                    'commission_percent' => $vendor->commission_percent,
                    'products_count' => $vendor->products()->count(),
                    'metadata' => $vendor->metadata,
                    'created_at' => $this->formatDateTimeOslo($vendor->created_at),
                    'updated_at' => $this->formatDateTimeOslo($vendor->updated_at),
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error in VendorsController@update', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to update vendor: ' . $e->getMessage()
            ], 500);
        }
    }
}
