<?php

namespace App\Http\Controllers\Api;

use App\Models\ConnectedProduct;
use App\Models\ConnectedPrice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
                        'price' => null,
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

        // Get image URLs
        $images = [];
        if ($product->hasMedia('images')) {
            $images = $product->getMedia('images')->map(function ($media) {
                // Use current request URL if available
                if (app()->runningInConsole() === false && request()->hasHeader('Host')) {
                    $scheme = request()->getScheme();
                    $host = request()->getHost();
                    $port = request()->getPort();
                    $baseUrl = $scheme . '://' . $host . ($port && $port != 80 && $port != 443 ? ':' . $port : '');
                    $path = $media->getPath();
                    $relativePath = str_replace(public_path('storage'), '', $path);
                    return $baseUrl . '/storage' . $relativePath;
                }
                return $media->getUrl();
            })->toArray();
        } elseif ($product->images && is_array($product->images)) {
            // Fallback to stored image URLs (from Stripe)
            $images = $product->images;
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
            'price' => $defaultPrice ? [
                'id' => $defaultPrice->stripe_price_id,
                'amount' => $defaultPrice->unit_amount,
                'amount_formatted' => number_format($defaultPrice->unit_amount / 100, 2, '.', ''),
                'currency' => strtoupper($defaultPrice->currency ?? 'NOK'),
            ] : null,
            'prices' => $allPrices,
            'tax_code' => $product->tax_code,
            'unit_label' => $product->unit_label,
            'statement_descriptor' => $product->statement_descriptor,
            'package_dimensions' => $product->package_dimensions,
            'product_meta' => $product->product_meta,
            'created_at' => $product->created_at?->toISOString(),
            'updated_at' => $product->updated_at?->toISOString(),
        ];
    }
}
