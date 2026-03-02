<?php

namespace App\Http\Controllers\Api;

use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class CollectionsController extends BaseApiController
{
    /**
     * Display a listing of collections
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $store = $this->getTenantStore($request);

            if (! $store) {
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

            // Filter by parent_id (null = root collections only)
            if ($request->has('parent_id')) {
                $parentId = $request->get('parent_id');
                $query->where('parent_id', $parentId === '' || $parentId === 'null' ? null : (int) $parentId);
            }

            $withChildren = filter_var($request->get('with_children', false), FILTER_VALIDATE_BOOLEAN);

            // Get paginated results
            $perPage = min($request->get('per_page', 50), 100); // Max 100 per page
            $collections = $query->withCount('products')
                ->when($withChildren, fn ($q) => $q->with(['children' => fn ($c) => $c->withCount('products')->orderBy('sort_order')->orderBy('name')]))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate($perPage);

            // Transform collections
            $transformedCollections = $collections->getCollection()->map(function ($collection) use ($withChildren) {
                $item = [
                    'id' => $collection->id,
                    'parent_id' => $collection->parent_id,
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
                if ($withChildren && $collection->relationLoaded('children')) {
                    $item['children'] = $collection->children->map(fn ($c) => [
                        'id' => $c->id,
                        'parent_id' => $c->parent_id,
                        'name' => $c->name,
                        'description' => $c->description,
                        'handle' => $c->handle,
                        'image_url' => $this->getCollectionImageUrl($c),
                        'active' => $c->active,
                        'sort_order' => $c->sort_order,
                        'products_count' => $c->products_count,
                        'metadata' => $c->metadata,
                        'created_at' => $this->formatDateTimeOslo($c->created_at),
                        'updated_at' => $this->formatDateTimeOslo($c->updated_at),
                    ])->values()->all();
                }
                return $item;
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

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $collection = Collection::where('stripe_account_id', $store->stripe_account_id)
            ->withCount('products')
            ->findOrFail($id);

        // Get products in this collection
        $productsController = new ProductsController;
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
                'parent_id' => $collection->parent_id,
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
     * Get all descendant collection IDs (children, grandchildren, etc.) to prevent cycles
     */
    protected function getCollectionDescendantIds(Collection $collection): array
    {
        $collection->loadMissing('children');
        $ids = [];
        foreach ($collection->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getCollectionDescendantIds($child));
        }
        return $ids;
    }

    /**
     * Get collection image URL - generate signed URL if local, keep external URLs as-is
     */
    protected function getCollectionImageUrl(Collection $collection): ?string
    {
        if (! $collection->image_url) {
            return null;
        }

        // If it's an external URL (Stripe, CDN, etc.), return as-is
        $storageUrl = Storage::disk('public')->url('');
        if (! str_starts_with($collection->image_url, $storageUrl) &&
            ! str_starts_with($collection->image_url, config('app.url')) &&
            ! str_starts_with($collection->image_url, request()->getSchemeAndHttpHost())) {
            return $collection->image_url;
        }

        // If it's a local storage URL, generate a signed URL
        if (str_starts_with($collection->image_url, $storageUrl) ||
            str_starts_with($collection->image_url, config('app.url')) ||
            str_starts_with($collection->image_url, request()->getSchemeAndHttpHost())) {
            // Generate signed URL with stable 24h expiry (same URL all day for caching)
            return URL::temporarySignedRoute(
                'api.collections.image.serve',
                now()->startOfDay()->addDay(),
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

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'handle' => 'nullable|string|max:255|unique:collections,handle,NULL,id,stripe_account_id,'.$store->stripe_account_id,
            'parent_id' => 'nullable|integer|exists:collections,id',
            'image_url' => 'nullable|url',
            'active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        // Ensure parent belongs to same store
        if (! empty($validated['parent_id'])) {
            $parent = Collection::where('stripe_account_id', $store->stripe_account_id)->find($validated['parent_id']);
            if (! $parent) {
                return response()->json(['error' => 'Parent collection not found or not in this store.'], 422);
            }
        }

        try {
            $collection = new Collection;
            $collection->store_id = $store->id;
            $collection->stripe_account_id = $store->stripe_account_id;
            $collection->parent_id = $validated['parent_id'] ?? null;
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
                    'parent_id' => $collection->parent_id,
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
                'error' => 'Failed to create collection: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified collection
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $collection = Collection::where('stripe_account_id', $store->stripe_account_id)
            ->findOrFail($id);
        $collection->load('children'); // for cycle check when updating parent_id

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'handle' => 'nullable|string|max:255|unique:collections,handle,'.$id.',id,stripe_account_id,'.$store->stripe_account_id,
            'parent_id' => 'nullable|integer|exists:collections,id',
            'image_url' => 'nullable|url',
            'active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        // Prevent setting parent to self or any descendant
        if (array_key_exists('parent_id', $validated)) {
            $newParentId = $validated['parent_id'];
            if ((int) $newParentId === (int) $id) {
                return response()->json(['error' => 'Collection cannot be its own parent.'], 422);
            }
            if ($newParentId) {
                $parent = Collection::where('stripe_account_id', $store->stripe_account_id)->find($newParentId);
                if (! $parent) {
                    return response()->json(['error' => 'Parent collection not found or not in this store.'], 422);
                }
                $descendantIds = $this->getCollectionDescendantIds($collection);
                if (in_array((int) $newParentId, $descendantIds, true)) {
                    return response()->json(['error' => 'Cannot set parent to a descendant (would create a cycle).'], 422);
                }
            }
        }

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
            if (array_key_exists('parent_id', $validated)) {
                $collection->parent_id = $validated['parent_id'] ?: null;
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
                    'parent_id' => $collection->parent_id,
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
                'error' => 'Failed to update collection: '.$e->getMessage(),
            ], 500);
        }
    }
}
