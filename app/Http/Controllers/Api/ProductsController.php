<?php

namespace App\Http\Controllers\Api;

use App\Models\ConnectedProduct;
use App\Models\ConnectedPrice;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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

            // Filter by search term if provided
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            // Filter by type if provided
            if ($request->has('type')) {
                $query->where('type', $request->get('type'));
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
                    return [
                        'id' => $product->id,
                        'stripe_product_id' => $product->stripe_product_id,
                        'name' => $product->name,
                        'description' => $product->description,
                        'type' => $product->type,
                        'active' => $product->active,
                        'product_price' => null,
                        'prices' => [],
                        'images' => [],
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
    protected function transformProductForPos(ConnectedProduct $product): array
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
                // Generate signed URL that expires in 1 hour
                return URL::temporarySignedRoute(
                    'api.products.images.serve',
                    now()->addHour(),
                    [
                        'product' => $product->id,
                        'media' => $media->id,
                    ]
                );
            })->toArray();
        } elseif ($product->images && is_array($product->images)) {
            // Fallback to stored image URLs (from Stripe) - these are external URLs, keep as-is
            $images = $product->images;
        }

        // Get variants with inventory
        $variants = ProductVariant::where('connected_product_id', $product->id)
            ->where('stripe_account_id', $product->stripe_account_id)
            ->where('active', true)
            ->get()
            ->map(function ($variant) use ($product) {
                return [
                    'id' => $variant->id,
                    'stripe_product_id' => $variant->stripe_product_id, // Each variant is a separate Stripe Product
                    'stripe_price_id' => $variant->stripe_price_id,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'variant_name' => $variant->variant_name,
                    'options' => [
                        'option1' => $variant->option1_name ? [
                            'name' => $variant->option1_name,
                            'value' => $variant->option1_value,
                        ] : null,
                        'option2' => $variant->option2_name ? [
                            'name' => $variant->option2_name,
                            'value' => $variant->option2_value,
                        ] : null,
                        'option3' => $variant->option3_name ? [
                            'name' => $variant->option3_name,
                            'value' => $variant->option3_value,
                        ] : null,
                    ],
                    'variant_price' => [
                        'amount' => $variant->price_amount,
                        'amount_formatted' => $variant->formatted_price,
                        'currency' => strtoupper($variant->currency ?? 'NOK'),
                        'compare_at_price' => $variant->compare_at_price_amount 
                            ? number_format($variant->compare_at_price_amount / 100, 2, '.', '') 
                            : null,
                        'discount_percentage' => $variant->discount_percentage,
                    ],
                    'variant_inventory' => [
                        'quantity' => $variant->inventory_quantity,
                        'in_stock' => $variant->in_stock,
                        'policy' => $variant->inventory_policy,
                        'management' => $variant->inventory_management,
                        'tracked' => $variant->inventory_quantity !== null,
                    ],
                    'weight_grams' => $variant->weight_grams,
                    'requires_shipping' => $variant->requires_shipping,
                    'taxable' => $variant->taxable,
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

        return [
            'id' => $product->id,
            'stripe_product_id' => $product->stripe_product_id,
            'name' => $product->name,
            'description' => $product->description,
            'type' => $product->type,
            'active' => $product->active,
            'shippable' => $product->shippable ?? false,
            'url' => $product->url,
            'images' => $images,
            'product_price' => $defaultPrice ? [
                'id' => $defaultPrice->stripe_price_id,
                'amount' => $defaultPrice->unit_amount,
                'amount_formatted' => number_format($defaultPrice->unit_amount / 100, 2, '.', ''),
                'currency' => strtoupper($defaultPrice->currency ?? 'NOK'),
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
            'tax_code' => $product->tax_code,
            'unit_label' => $product->unit_label,
            'statement_descriptor' => $product->statement_descriptor,
            'package_dimensions' => is_array($product->package_dimensions) 
                ? json_encode($product->package_dimensions) 
                : (string) ($product->package_dimensions ?? ''),
            'product_meta' => $product->product_meta,
            'created_at' => $product->created_at?->toISOString(),
            'updated_at' => $product->updated_at?->toISOString(),
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

        // If it's an external URL (Stripe, etc.), return as-is
        if (filter_var($variant->image_url, FILTER_VALIDATE_URL) && 
            !str_starts_with($variant->image_url, config('app.url')) &&
            !str_starts_with($variant->image_url, request()->getSchemeAndHttpHost())) {
            return $variant->image_url;
        }

        // If it's a local file path, we'd need to handle it differently
        // For now, return as-is if it's already a URL
        // If variants store local files in the future, we can add signed URL generation here
        return $variant->image_url;
    }
}
