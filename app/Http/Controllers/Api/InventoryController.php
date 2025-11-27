<?php

namespace App\Http\Controllers\Api;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class InventoryController extends BaseApiController
{
    /**
     * Update inventory for a variant
     */
    public function updateVariant(Request $request, string $variantId): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $variant = ProductVariant::where('id', $variantId)
            ->where('stripe_account_id', $store->stripe_account_id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'inventory_quantity' => 'nullable|integer|min:0',
            'inventory_policy' => 'nullable|in:deny,continue',
            'inventory_management' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $variant->update($validator->validated());

        return response()->json([
            'variant' => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'inventory' => [
                    'quantity' => $variant->inventory_quantity,
                    'in_stock' => $variant->in_stock,
                    'policy' => $variant->inventory_policy,
                    'management' => $variant->inventory_management,
                    'tracked' => $variant->inventory_quantity !== null,
                ],
            ],
        ]);
    }

    /**
     * Adjust inventory (add or subtract)
     */
    public function adjustInventory(Request $request, string $variantId): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $variant = ProductVariant::where('id', $variantId)
            ->where('stripe_account_id', $store->stripe_account_id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer',
            'reason' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $adjustment = $validator->validated()['quantity'];
        $currentQuantity = $variant->inventory_quantity ?? 0;
        $newQuantity = max(0, $currentQuantity + $adjustment); // Prevent negative

        $variant->update([
            'inventory_quantity' => $newQuantity,
        ]);

        // Log inventory adjustment (you might want to create an InventoryAdjustment model)
        \Log::info('Inventory adjusted', [
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'adjustment' => $adjustment,
            'old_quantity' => $currentQuantity,
            'new_quantity' => $newQuantity,
            'reason' => $validator->validated()['reason'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'variant' => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'inventory' => [
                    'quantity' => $variant->inventory_quantity,
                    'in_stock' => $variant->in_stock,
                    'previous_quantity' => $currentQuantity,
                    'adjustment' => $adjustment,
                ],
            ],
        ]);
    }

    /**
     * Set inventory quantity directly
     */
    public function setInventory(Request $request, string $variantId): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $variant = ProductVariant::where('id', $variantId)
            ->where('stripe_account_id', $store->stripe_account_id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldQuantity = $variant->inventory_quantity ?? 0;
        $newQuantity = $validator->validated()['quantity'];

        $variant->update([
            'inventory_quantity' => $newQuantity,
        ]);

        // Log inventory change
        \Log::info('Inventory set', [
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'reason' => $validator->validated()['reason'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'variant' => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'inventory' => [
                    'quantity' => $variant->inventory_quantity,
                    'in_stock' => $variant->in_stock,
                    'previous_quantity' => $oldQuantity,
                ],
            ],
        ]);
    }

    /**
     * Get inventory for a product (all variants)
     */
    public function getProductInventory(Request $request, string $productId): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $product = \App\Models\ConnectedProduct::where('stripe_account_id', $store->stripe_account_id)
            ->where(function ($query) use ($productId) {
                $query->where('id', $productId)
                      ->orWhere('stripe_product_id', $productId);
            })
            ->firstOrFail();

        $variants = ProductVariant::where('connected_product_id', $product->id)
            ->where('stripe_account_id', $store->stripe_account_id)
            ->get()
            ->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'variant_name' => $variant->variant_name,
                    'inventory' => [
                        'quantity' => $variant->inventory_quantity,
                        'in_stock' => $variant->in_stock,
                        'policy' => $variant->inventory_policy,
                        'management' => $variant->inventory_management,
                        'tracked' => $variant->inventory_quantity !== null,
                    ],
                ];
            });

        $totalQuantity = $variants->sum(fn($v) => $v['inventory']['quantity'] ?? 0);
        $trackingInventory = $variants->contains(fn($v) => $v['inventory']['tracked']);

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
            ],
            'variants' => $variants,
            'summary' => [
                'total_quantity' => $trackingInventory ? $totalQuantity : null,
                'tracking_inventory' => $trackingInventory,
                'variants_count' => $variants->count(),
                'in_stock_count' => $variants->filter(fn($v) => $v['inventory']['in_stock'])->count(),
                'out_of_stock_count' => $variants->filter(fn($v) => !$v['inventory']['in_stock'] && $v['inventory']['tracked'])->count(),
            ],
        ]);
    }

    /**
     * Bulk update inventory for multiple variants
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validator = Validator::make($request->all(), [
            'variants' => 'required|array',
            'variants.*.variant_id' => 'required|exists:product_variants,id',
            'variants.*.quantity' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updated = [];
        $errors = [];

        foreach ($request->input('variants') as $variantData) {
            try {
                $variant = ProductVariant::where('id', $variantData['variant_id'])
                    ->where('stripe_account_id', $store->stripe_account_id)
                    ->first();

                if (!$variant) {
                    $errors[] = [
                        'variant_id' => $variantData['variant_id'],
                        'error' => 'Variant not found or not accessible',
                    ];
                    continue;
                }

                $variant->update([
                    'inventory_quantity' => $variantData['quantity'],
                ]);

                $updated[] = [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'inventory_quantity' => $variant->inventory_quantity,
                    'in_stock' => $variant->in_stock,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'variant_id' => $variantData['variant_id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'updated' => $updated,
            'errors' => $errors,
            'summary' => [
                'updated_count' => count($updated),
                'error_count' => count($errors),
            ],
        ]);
    }
}

