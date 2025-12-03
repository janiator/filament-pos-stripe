<?php

namespace App\Http\Controllers\Api;

use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CollectionsController extends BaseApiController
{
    /**
     * Display a listing of collections
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
            $query = Collection::where('stripe_account_id', $store->stripe_account_id);

            // Filter by active status if provided
            if ($request->has('active')) {
                $query->where('active', filter_var($request->get('active'), FILTER_VALIDATE_BOOLEAN));
            } else {
                // Default to active only
                $query->where('active', true);
            }

            // Filter by search term if provided
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            // Get paginated results
            $perPage = min($request->get('per_page', 50), 100); // Max 100 per page
            $collections = $query->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate($perPage);

            // Transform collections
            $transformedCollections = $collections->getCollection()->map(function ($collection) {
                return [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'description' => $collection->description,
                    'handle' => $collection->handle,
                    'image_url' => $collection->image_url,
                    'active' => $collection->active,
                    'sort_order' => $collection->sort_order,
                    'products_count' => $collection->products_count,
                    'metadata' => $collection->metadata,
                    'created_at' => $this->formatDateTimeOslo($collection->created_at),
                    'updated_at' => $this->formatDateTimeOslo($collection->updated_at),
                ];
            });

            return response()->json([
                'collections' => $transformedCollections,
                'meta' => [
                    'current_page' => $collections->currentPage(),
                    'last_page' => $collections->lastPage(),
                    'per_page' => $collections->perPage(),
                    'total' => $collections->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error in CollectionsController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Display the specified collection
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $collection = Collection::where('stripe_account_id', $store->stripe_account_id)
            ->withCount('products')
            ->findOrFail($id);

        // Get products in this collection
        $productsController = new ProductsController();
        $products = $collection->products()
            ->where('active', true)
            ->orderByPivot('sort_order')
            ->get()
            ->map(function ($product) use ($productsController) {
                return $productsController->transformProductForPos($product);
            });

        return response()->json([
            'collection' => [
                'id' => $collection->id,
                'name' => $collection->name,
                'description' => $collection->description,
                'handle' => $collection->handle,
                'image_url' => $collection->image_url,
                'active' => $collection->active,
                'sort_order' => $collection->sort_order,
                'products_count' => $collection->products_count,
                'metadata' => $collection->metadata,
                'products' => $products,
                'created_at' => $this->formatDateTimeOslo($collection->created_at),
                'updated_at' => $this->formatDateTimeOslo($collection->updated_at),
            ],
        ]);
    }
}

