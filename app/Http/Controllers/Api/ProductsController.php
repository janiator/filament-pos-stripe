<?php

namespace App\Http\Controllers\Api;

use App\Models\ConnectedProduct;
use App\Models\ConnectedPrice;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ProductsController extends BaseApiController
{
    /**
     * Display a listing of products for POS
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
            $query = ConnectedProduct::where('stripe_account_id', $store->stripe_account_id)
                ->where('active', true); // Only active products for POS

            // Filter by freetext search term if provided
            // Searches across product fields and variant fields (SKU, barcode, variant names, etc.)
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search, $store) {
                    // Search in product fields
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('description', 'ilike', "%{$search}%")
                      ->orWhere('stripe_product_id', 'ilike', "%{$search}%")
                      ->orWhere('product_code', 'ilike', "%{$search}%")
                      ->orWhere('article_group_code', 'ilike', "%{$search}%")
                      // Search in variant fields (SKU, barcode, option values, Stripe IDs)
                      // Note: variant_name is a computed attribute, so we search option values instead
                      ->orWhereHas('variants', function ($variantQuery) use ($search, $store) {
                          $variantQuery->where('stripe_account_id', $store->stripe_account_id)
                              ->where(function ($vq) use ($search) {
                                  $vq->where('sku', 'ilike', "%{$search}%")
                                     ->orWhere('barcode', 'ilike', "%{$search}%")
                                     ->orWhere('option1_value', 'ilike', "%{$search}%")
                                     ->orWhere('option2_value', 'ilike', "%{$search}%")
                                     ->orWhere('option3_value', 'ilike', "%{$search}%")
                                     ->orWhere('stripe_product_id', 'ilike', "%{$search}%")
                                     ->orWhere('stripe_price_id', 'ilike', "%{$search}%");
                              });
                      });
                });
            }

            // Filter by type if provided
            if ($request->has('type')) {
                $query->where('type', $request->get('type'));
            }

            // Filter by collection if provided (by ID or slug/handle)
            if ($request->has('collection_id') || $request->has('collection_slug')) {
                // Special handling for collection_id=0 (uncategorized products)
                if ($request->has('collection_id') && $request->get('collection_id') == 0) {
                    $query->doesntHave('collections');
                } else {
                    $query->whereHas('collections', function ($q) use ($request, $store) {
                        $q->where('collections.stripe_account_id', $store->stripe_account_id);
                        
                        if ($request->has('collection_id')) {
                            $q->where('collections.id', $request->get('collection_id'));
                        }
                        
                        if ($request->has('collection_slug')) {
                            $q->where('collections.handle', $request->get('collection_slug'));
                        }
                    });
                }
            }

            // Get paginated results
            $perPage = min($request->get('per_page', 50), 100); // Max 100 per page
            $products = $query->orderBy('name')
                ->paginate($perPage);

            // Transform products for POS
            $transformedProducts = $products->getCollection()->map(function ($product) {
                try {
                    return $this->transformProductForPos($product);
                } catch (\Throwable $e) {
                    \Log::error('Error transforming product for POS', [
                        'product_id' => $product->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Return minimal product data if transformation fails
                    // Ensure consistent structure even on error
                    return [
                        'id' => $product->id,
                        'stripe_product_id' => $product->stripe_product_id ?? null,
                        'name' => $product->name ?? '',
                        'description' => $product->description ?? null,
                        'type' => $product->type ?? 'service',
                        'active' => $product->active ?? false,
                        'shippable' => $product->shippable ?? false,
                        'url' => $product->url ?? null,
                        'images' => [],
                        'product_price' => null,
                        'prices' => [],
                        'variants' => [],
                        'variants_count' => 0,
                        'product_inventory' => [
                            'tracked' => false,
                            'total_quantity' => null,
                            'in_stock_variants' => 0,
                            'out_of_stock_variants' => 0,
                            'all_in_stock' => null,
                        ],
                        'tax_code' => $product->tax_code ?? null,
                        'unit_label' => $product->unit_label ?? null,
                        'statement_descriptor' => $product->statement_descriptor ?? null,
                        'package_dimensions' => null,
                        'product_meta' => $product->product_meta ?? null,
                        'created_at' => $this->formatDateTimeOslo($product->created_at),
                        'updated_at' => $this->formatDateTimeOslo($product->updated_at),
                    ];
                }
            });

            return response()->json([
                'product' => $transformedProducts,
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error in ProductsController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Let the exception handler deal with it
        }
    }

    /**
     * Display the specified product
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        // Try to find by ID first, then by stripe_product_id
        $product = ConnectedProduct::where('stripe_account_id', $store->stripe_account_id)
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                      ->orWhere('stripe_product_id', $id);
            })
            ->firstOrFail();

        return response()->json([
            'product' => $this->transformProductForPos($product),
        ]);
    }

    /**
     * Transform product for POS display
     */
    public function transformProductForPos(ConnectedProduct $product): array
    {
        // Get default/active price
        $defaultPrice = null;

        if ($product->stripe_product_id) {
            // First try to get the default_price if set
            if ($product->default_price) {
                $defaultPrice = ConnectedPrice::where('stripe_price_id', $product->default_price)
                    ->where('stripe_account_id', $product->stripe_account_id)
                    ->where('active', true)
                    ->first();
            }
            
            // If no default price found, get the first active price
            if (!$defaultPrice) {
                $defaultPrice = ConnectedPrice::where('stripe_product_id', $product->stripe_product_id)
                    ->where('stripe_account_id', $product->stripe_account_id)
                    ->where('active', true)
                    ->orderBy('created_at', 'desc')
                    ->first();
            }
        }

        // Get all active prices for this product (only if stripe_product_id exists)
        $activePrices = collect([]);
        if ($product->stripe_product_id) {
            $activePrices = ConnectedPrice::where('stripe_product_id', $product->stripe_product_id)
                ->where('stripe_account_id', $product->stripe_account_id)
                ->where('active', true)
                ->orderBy('unit_amount')
                ->get();
        }

        // Transform prices
        $allPrices = $activePrices->map(function ($price) use ($product) {
            return [
                'id' => $price->stripe_price_id,
                'amount' => $price->unit_amount,
                'amount_formatted' => number_format($price->unit_amount / 100, 2, '.', ''),
                'currency' => strtoupper($price->currency ?? 'NOK'),
                'type' => $price->type,
                'is_default' => $price->stripe_price_id === $product->default_price,
            ];
        })->values();

        // Get image URLs with signed URLs for security
        $images = [];
        if ($product->hasMedia('images')) {
            $images = $product->getMedia('images')->map(function ($media) use ($product) {
                // Generate signed URL that expires in 24 hours
                return URL::temporarySignedRoute(
                    'api.products.images.serve',
                    now()->addDay(),
                    [
                        'product' => $product->id,
                        'media' => $media->id,
                    ]
                );
            })->toArray();
        } elseif ($product->images && is_array($product->images)) {
            // Fallback to stored image URLs (from Stripe) - ensure they're strings
            $images = array_map(function ($image) {
                // If image is an object/array, extract URL; otherwise return as string
                if (is_array($image) && isset($image['url'])) {
                    return $image['url'];
                } elseif (is_object($image) && isset($image->url)) {
                    return $image->url;
                }
                return is_string($image) ? $image : (string) $image;
            }, $product->images);
        }

        // Get collections
        $collections = $product->collections()->get()->map(function ($collection) {
            return [
                'id' => $collection->id,
                'name' => $collection->name,
                'handle' => $collection->handle,
            ];
        })->values();

        // Get variants with inventory
        $variants = ProductVariant::where('connected_product_id', $product->id)
            ->where('stripe_account_id', $product->stripe_account_id)
            ->where('active', true)
            ->get()
            ->map(function ($variant) use ($product) {
                // Price handling for variants:
                // - If price_amount is null (custom price input), return 0 and "0.00"
                // - Frontend can check if price_amount === 0 to enable custom price input
                // - This avoids null type issues with FlutterFlow's non-nullable schema
                $priceAmount = $variant->price_amount ?? 0;
                $currency = strtoupper($variant->currency ?? 'NOK');
                
                // Format price - return "0.00" if no price set (frontend checks price_amount === 0)
                $amountFormatted = $priceAmount > 0
                    ? number_format($priceAmount / 100, 2, '.', '') 
                    : '0.00';
                
                // Format compare_at_price consistently
                $compareAtPriceFormatted = null;
                if ($variant->compare_at_price_amount && $variant->compare_at_price_amount > 0) {
                    $compareAtPriceFormatted = number_format($variant->compare_at_price_amount / 100, 2, '.', '');
                }
                
                return [
                    'id' => $variant->id,
                    'stripe_product_id' => $variant->stripe_product_id ?? null,
                    'stripe_price_id' => $variant->stripe_price_id ?? null,
                    'sku' => $variant->sku ?? null,
                    'barcode' => $variant->barcode ?? null,
                    'variant_name' => $variant->variant_name ?? 'Default',
                    'variant_options' => array_values(array_filter([
                        $variant->option1_name ? [
                            'name' => $variant->option1_name,
                            'value' => $variant->option1_value ?? '',
                        ] : null,
                        $variant->option2_name ? [
                            'name' => $variant->option2_name,
                            'value' => $variant->option2_value ?? '',
                        ] : null,
                        $variant->option3_name ? [
                            'name' => $variant->option3_name,
                            'value' => $variant->option3_value ?? '',
                        ] : null,
                    ], fn($option) => $option !== null)),
                    // Variant price object (for FlutterFlow compatibility)
                    'variant_price' => [
                        'amount' => $priceAmount, // Returns 0 for custom price input
                        'amount_formatted' => $amountFormatted, // Returns "0.00" for custom price input
                    ],
                    // Also include flattened fields for backward compatibility
                    'price_amount' => $priceAmount,
                    'price_amount_formatted' => $amountFormatted,
                    'compare_at_price_amount' => $variant->compare_at_price_amount, // Can be null
                    'compare_at_price_amount_formatted' => $compareAtPriceFormatted, // Can be null
                    'currency' => $currency,
                    'no_price_in_pos' => $variant->no_price_in_pos ?? false,
                    'variant_inventory' => [
                        'quantity' => $variant->inventory_quantity ?? null,
                        'in_stock' => $variant->in_stock ?? true,
                        'policy' => $variant->inventory_policy ?? null,
                        'management' => $variant->inventory_management ?? null,
                        'tracked' => $variant->inventory_quantity !== null,
                    ],
                    'weight_grams' => $variant->weight_grams ?? null,
                    'requires_shipping' => $variant->requires_shipping ?? false,
                    'taxable' => $variant->taxable ?? false,
                    'image_url' => $this->getVariantImageUrl($variant),
                ];
            })
            ->values();

        // Calculate total inventory if tracking
        $totalInventory = null;
        $inStockVariants = 0;
        $outOfStockVariants = 0;
        $trackingInventory = false;

        foreach ($variants as $variant) {
            if ($variant['variant_inventory']['tracked']) {
                $trackingInventory = true;
                if ($totalInventory === null) {
                    $totalInventory = 0;
                }
                $totalInventory += $variant['variant_inventory']['quantity'] ?? 0;
                
                if ($variant['variant_inventory']['in_stock']) {
                    $inStockVariants++;
                } else {
                    $outOfStockVariants++;
                }
            }
        }

        // Format package_dimensions consistently
        $packageDimensions = null;
        if ($product->package_dimensions) {
            if (is_array($product->package_dimensions)) {
                $packageDimensions = json_encode($product->package_dimensions);
            } else {
                $packageDimensions = (string) $product->package_dimensions;
            }
        }

        return [
            'id' => $product->id,
            'stripe_product_id' => $product->stripe_product_id ?? null,
            'name' => $product->name ?? '',
            'description' => $product->description ?? null,
            'type' => $product->type ?? 'service',
            'active' => $product->active ?? false,
            'shippable' => $product->shippable ?? false,
            'url' => $product->url ?? null,
            'images' => $images,
            'no_price_in_pos' => $product->no_price_in_pos ?? false,
            'product_price' => $defaultPrice ? [
                'id' => $defaultPrice->stripe_price_id,
                'amount' => $defaultPrice->unit_amount,
                'amount_formatted' => number_format($defaultPrice->unit_amount / 100, 2, '.', ''),
                'currency' => strtoupper($defaultPrice->currency ?? 'NOK'),
                'type' => $defaultPrice->type ?? 'one_time',
                'is_default' => true, // product_price is always the default price
            ] : null,
            'prices' => $allPrices,
            'variants' => $variants,
            'variants_count' => $variants->count(),
            'product_inventory' => [
                'tracked' => $trackingInventory,
                'total_quantity' => $totalInventory,
                'in_stock_variants' => $inStockVariants,
                'out_of_stock_variants' => $outOfStockVariants,
                'all_in_stock' => $trackingInventory ? ($outOfStockVariants === 0) : null,
            ],
            'tax_code' => $product->tax_code ?? null,
            'unit_label' => $product->unit_label ?? null,
            'statement_descriptor' => $product->statement_descriptor ?? null,
            'package_dimensions' => $packageDimensions,
            'product_meta' => $product->product_meta ?? null,
            'collections' => $collections,
            'created_at' => $this->formatDateTimeOslo($product->created_at),
            'updated_at' => $this->formatDateTimeOslo($product->updated_at),
        ];
    }

    /**
     * Get variant image URL - generate signed URL if local, keep external URLs as-is
     */
    protected function getVariantImageUrl(ProductVariant $variant): ?string
    {
        if (!$variant->image_url) {
            return null;
        }

        // If it's an external URL (Stripe, CDN, etc.), return as-is
        if (filter_var($variant->image_url, FILTER_VALIDATE_URL) && 
            !str_starts_with($variant->image_url, config('app.url')) &&
            !str_starts_with($variant->image_url, request()->getSchemeAndHttpHost())) {
            return $variant->image_url;
        }

        // If it's a local storage URL, check if it's a product media file
        // Variants typically use external URLs, but if they reference product media, use product image route
        $storageUrl = Storage::disk('public')->url('');
        if (str_starts_with($variant->image_url, $storageUrl) ||
            str_starts_with($variant->image_url, config('app.url')) ||
            str_starts_with($variant->image_url, request()->getSchemeAndHttpHost())) {
            // Try to find if this variant's product has media that matches
            // For now, return as-is since variants typically use external URLs
            // If variants start storing local media files, we'll need a variant image route
            return $variant->image_url;
        }

        // Fallback: return as-is
        return $variant->image_url;
    }

    /**
     * Store a newly created product
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
            'type' => 'nullable|string|in:service,good',
            'active' => 'nullable|boolean',
            'shippable' => 'nullable|boolean',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'url' => 'nullable|url',
            'tax_code' => 'nullable|string',
            'unit_label' => 'nullable|string|max:12',
            'statement_descriptor' => 'nullable|string|max:22',
            'no_price_in_pos' => 'nullable|boolean',
            'product_code' => 'nullable|string',
            'article_group_code' => 'nullable|string',
            'collection_ids' => 'nullable|array',
            'collection_ids.*' => 'exists:collections,id',
        ]);

        try {
            // Create product model
            $product = new ConnectedProduct();
            $product->stripe_account_id = $store->stripe_account_id;
            $product->name = $validated['name'];
            $product->description = $validated['description'] ?? null;
            $product->type = $validated['type'] ?? 'service';
            $product->active = $validated['active'] ?? true;
            $product->shippable = $validated['shippable'] ?? false;
            $product->url = $validated['url'] ?? null;
            $product->tax_code = $validated['tax_code'] ?? null;
            $product->unit_label = $validated['unit_label'] ?? null;
            $product->statement_descriptor = $validated['statement_descriptor'] ?? null;
            $product->no_price_in_pos = $validated['no_price_in_pos'] ?? false;
            $product->product_code = $validated['product_code'] ?? null;
            $product->article_group_code = $validated['article_group_code'] ?? null;
            
            // Set price if provided
            if (isset($validated['price']) && !$product->no_price_in_pos) {
                $product->price = $validated['price'];
                $product->currency = strtolower($validated['currency'] ?? 'nok');
            }

            // Create product in Stripe first
            $createAction = new \App\Actions\ConnectedProducts\CreateConnectedProductInStripe();
            $stripeProductId = $createAction($product);

            if (!$stripeProductId) {
                return response()->json([
                    'error' => 'Failed to create product in Stripe'
                ], 500);
            }

            $product->stripe_product_id = $stripeProductId;
            $product->save();

            // Create price in Stripe if price is provided
            if (isset($validated['price']) && !$product->no_price_in_pos) {
                $createPriceAction = new \App\Actions\ConnectedPrices\CreateConnectedPriceInStripe();
                $priceId = $createPriceAction(
                    $product->stripe_account_id,
                    $stripeProductId,
                    (int) round($validated['price'] * 100),
                    strtolower($validated['currency'] ?? 'nok')
                );

                if ($priceId) {
                    $product->default_price = $priceId;
                    $product->saveQuietly();
                }
            }

            // Attach collections if provided
            if (isset($validated['collection_ids']) && !empty($validated['collection_ids'])) {
                $product->collections()->sync($validated['collection_ids']);
            }

            return response()->json([
                'product' => $this->transformProductForPos($product->fresh()),
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('Error in ProductsController@store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to create product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $product = ConnectedProduct::where('stripe_account_id', $store->stripe_account_id)
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                      ->orWhere('stripe_product_id', $id);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:service,good',
            'active' => 'nullable|boolean',
            'shippable' => 'nullable|boolean',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'url' => 'nullable|url',
            'tax_code' => 'nullable|string',
            'unit_label' => 'nullable|string|max:12',
            'statement_descriptor' => 'nullable|string|max:22',
            'no_price_in_pos' => 'nullable|boolean',
            'product_code' => 'nullable|string',
            'article_group_code' => 'nullable|string',
            'collection_ids' => 'nullable|array',
            'collection_ids.*' => 'exists:collections,id',
        ]);

        try {
            // Update product fields
            if (isset($validated['name'])) {
                $product->name = $validated['name'];
            }
            if (isset($validated['description'])) {
                $product->description = $validated['description'];
            }
            if (isset($validated['type'])) {
                $product->type = $validated['type'];
            }
            if (isset($validated['active'])) {
                $product->active = $validated['active'];
            }
            if (isset($validated['shippable'])) {
                $product->shippable = $validated['shippable'];
            }
            if (isset($validated['url'])) {
                $product->url = $validated['url'];
            }
            if (isset($validated['tax_code'])) {
                $product->tax_code = $validated['tax_code'];
            }
            if (isset($validated['unit_label'])) {
                $product->unit_label = $validated['unit_label'];
            }
            if (isset($validated['statement_descriptor'])) {
                $product->statement_descriptor = $validated['statement_descriptor'];
            }
            if (isset($validated['no_price_in_pos'])) {
                $product->no_price_in_pos = $validated['no_price_in_pos'];
            }
            if (isset($validated['product_code'])) {
                $product->product_code = $validated['product_code'];
            }
            if (isset($validated['article_group_code'])) {
                $product->article_group_code = $validated['article_group_code'];
            }

            // Update price if provided
            if (isset($validated['price']) && !$product->no_price_in_pos) {
                $product->price = $validated['price'];
                $product->currency = strtolower($validated['currency'] ?? 'nok');
            }

            // Save product (this will trigger Stripe sync via listener)
            $product->save();

            // Update collections if provided
            if (isset($validated['collection_ids'])) {
                $product->collections()->sync($validated['collection_ids']);
            }

            return response()->json([
                'product' => $this->transformProductForPos($product->fresh()),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error in ProductsController@update', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to update product: ' . $e->getMessage()
            ], 500);
        }
    }
}
