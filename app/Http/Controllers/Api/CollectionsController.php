<?php

namespace App\Http\Controllers\Api;

use App\Models\Collection;
use App\Models\ConnectedProduct;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

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
                    'image_url' => $this->getCollectionImageUrl($collection),
                    'active' => $collection->active,
                    'sort_order' => $collection->sort_order,
                    'products_count' => $collection->products_count,
                    'metadata' => $collection->metadata,
                    'created_at' => $this->formatDateTimeOslo($collection->created_at),
                    'updated_at' => $this->formatDateTimeOslo($collection->updated_at),
                ];
            });

            // Check if there are products with no collection
            $uncategorizedCount = \App\Models\ConnectedProduct::where('stripe_account_id', $store->stripe_account_id)
                ->where('active', true)
                ->doesntHave('collections')
                ->count();

            // Add fake "Ukategorisert" category if there are uncategorized products
            if ($uncategorizedCount > 0) {
                $uncategorizedCollection = [
                    'id' => 0,
                    'name' => 'Ukategorisert',
                    'description' => null,
                    'handle' => null,
                    'image_url' => null,
                    'active' => true,
                    'sort_order' => 9999, // Put it at the end
                    'products_count' => $uncategorizedCount,
                    'metadata' => null,
                    'created_at' => null,
                    'updated_at' => null,
                ];
                
                // Append to the end of the collection
                $transformedCollections = $transformedCollections->push($uncategorizedCollection);
            }

            return response()->json([
                'collections' => $transformedCollections,
                'meta' => [
                    'current_page' => $collections->currentPage(),
                    'last_page' => $collections->lastPage(),
                    'per_page' => $collections->perPage(),
                    'total' => $collections->total() + ($uncategorizedCount > 0 ? 1 : 0),
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
                'image_url' => $this->getCollectionImageUrl($collection),
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

    /**
     * Get collection image URL - generate signed URL if local, keep external URLs as-is
     */
    protected function getCollectionImageUrl(Collection $collection): ?string
    {
        if (!$collection->image_url) {
            return null;
        }

        // If it's an external URL (Stripe, CDN, etc.), return as-is
        $storageUrl = Storage::disk('public')->url('');
        if (!str_starts_with($collection->image_url, $storageUrl) && 
            !str_starts_with($collection->image_url, config('app.url')) &&
            !str_starts_with($collection->image_url, request()->getSchemeAndHttpHost())) {
            return $collection->image_url;
        }

        // If it's a local storage URL, generate a signed URL
        if (str_starts_with($collection->image_url, $storageUrl) ||
            str_starts_with($collection->image_url, config('app.url')) ||
            str_starts_with($collection->image_url, request()->getSchemeAndHttpHost())) {
            // Generate signed URL that expires in 24 hours
            return URL::temporarySignedRoute(
                'api.collections.image.serve',
                now()->addDay(),
                [
                    'collectionId' => $collection->id,
                ]
            );
        }

        // Fallback: return as-is
        return $collection->image_url;
    }

    /**
     * Store a newly created collection
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
            'handle' => 'nullable|string|max:255|unique:collections,handle,NULL,id,stripe_account_id,' . $store->stripe_account_id,
            'image_url' => 'nullable|url',
            'active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        try {
            $collection = new Collection();
            $collection->store_id = $store->id;
            $collection->stripe_account_id = $store->stripe_account_id;
            $collection->name = $validated['name'];
            $collection->description = $validated['description'] ?? null;
            $collection->handle = $validated['handle'] ?? \Str::slug($validated['name']);
            $collection->image_url = $validated['image_url'] ?? null;
            $collection->active = $validated['active'] ?? true;
            $collection->sort_order = $validated['sort_order'] ?? 0;
            $collection->metadata = $validated['metadata'] ?? null;
            $collection->save();

            return response()->json([
                'collection' => [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'description' => $collection->description,
                    'handle' => $collection->handle,
                    'image_url' => $this->getCollectionImageUrl($collection),
                    'active' => $collection->active,
                    'sort_order' => $collection->sort_order,
                    'products_count' => 0,
                    'metadata' => $collection->metadata,
                    'created_at' => $this->formatDateTimeOslo($collection->created_at),
                    'updated_at' => $this->formatDateTimeOslo($collection->updated_at),
                ],
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('Error in CollectionsController@store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to create collection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified collection
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $collection = Collection::where('stripe_account_id', $store->stripe_account_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'handle' => 'nullable|string|max:255|unique:collections,handle,' . $id . ',id,stripe_account_id,' . $store->stripe_account_id,
            'image_url' => 'nullable|url',
            'active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        try {
            if (isset($validated['name'])) {
                $collection->name = $validated['name'];
            }
            if (isset($validated['description'])) {
                $collection->description = $validated['description'];
            }
            if (isset($validated['handle'])) {
                $collection->handle = $validated['handle'];
            }
            if (isset($validated['image_url'])) {
                $collection->image_url = $validated['image_url'];
            }
            if (isset($validated['active'])) {
                $collection->active = $validated['active'];
            }
            if (isset($validated['sort_order'])) {
                $collection->sort_order = $validated['sort_order'];
            }
            if (isset($validated['metadata'])) {
                $collection->metadata = $validated['metadata'];
            }

            $collection->save();

            return response()->json([
                'collection' => [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'description' => $collection->description,
                    'handle' => $collection->handle,
                    'image_url' => $this->getCollectionImageUrl($collection),
                    'active' => $collection->active,
                    'sort_order' => $collection->sort_order,
                    'products_count' => $collection->products()->count(),
                    'metadata' => $collection->metadata,
                    'created_at' => $this->formatDateTimeOslo($collection->created_at),
                    'updated_at' => $this->formatDateTimeOslo($collection->updated_at),
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error in CollectionsController@update', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to update collection: ' . $e->getMessage()
            ], 500);
        }
    }
}

