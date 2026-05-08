<?php

namespace App\Http\Controllers\Api;

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\InventoryLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventoryController extends BaseApiController
{
    protected function ensureInventoryAddon(Store $store): ?JsonResponse
    {
        if (! Addon::storeHasActiveAddon($store->id, AddonType::Inventory)) {
            return response()->json([
                'error' => 'Inventory add-on is not enabled for this store.',
            ], 403);
        }

        return null;
    }

    /**
     * Update inventory for a variant
     */
    public function updateVariant(Request $request, string $variantId): JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        if ($response = $this->ensureInventoryAddon($store)) {
            return $response;
        }

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
        $variant->refresh();

        return response()->json([
            'variant' => $this->variantInventoryPayload($variant),
        ]);
    }

    /**
     * Adjust inventory (add or subtract)
     */
    public function adjustInventory(Request $request, string $variantId): JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        if ($response = $this->ensureInventoryAddon($store)) {
            return $response;
        }

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

        app(InventoryLedgerService::class)->recordManualAdjustment(
            $store,
            $variant,
            $adjustment,
            $validator->validated()['reason'] ?? null,
            $request->user()?->id
        );

        $variant->refresh();

        $payload = $this->variantInventoryPayload($variant);
        $payload['variant_inventory']['previous_quantity'] = $currentQuantity;
        $payload['variant_inventory']['adjustment'] = $adjustment;

        return response()->json([
            'variant' => $payload,
        ]);
    }

    /**
     * Set inventory quantity directly
     */
    public function setInventory(Request $request, string $variantId): JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        if ($response = $this->ensureInventoryAddon($store)) {
            return $response;
        }

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
        $note = $validator->validated()['note'] ?? $validator->validated()['reason'] ?? null;

        app(InventoryLedgerService::class)->recordManualSetQuantity(
            $store,
            $variant,
            $newQuantity,
            $note,
            $request->user()?->id
        );

        $variant->refresh();

        $payload = $this->variantInventoryPayload($variant);
        $payload['variant_inventory']['previous_quantity'] = $oldQuantity;

        return response()->json([
            'variant' => $payload,
        ]);
    }

    /**
     * Get inventory for a product (all variants)
     */
    public function getProductInventory(Request $request, string $productId): JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        if ($response = $this->ensureInventoryAddon($store)) {
            return $response;
        }

        $ledger = app(InventoryLedgerService::class);

        $product = \App\Models\ConnectedProduct::where('stripe_account_id', $store->stripe_account_id)
            ->where(function ($query) use ($productId) {
                $query->where('id', $productId)
                    ->orWhere('stripe_product_id', $productId);
            })
            ->firstOrFail();

        $variants = ProductVariant::where('connected_product_id', $product->id)
            ->where('stripe_account_id', $store->stripe_account_id)
            ->get()
            ->map(function ($variant) use ($ledger) {
                $tracked = $ledger->isVariantTracked($variant);

                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku ?? null,
                    'barcode' => $variant->barcode ?? null,
                    'variant_name' => $variant->variant_name ?? 'Default',
                    'variant_inventory' => [
                        'quantity' => $variant->inventory_quantity ?? null,
                        'in_stock' => $variant->in_stock ?? true,
                        'policy' => $variant->inventory_policy ?? null,
                        'management' => $variant->inventory_management ?? null,
                        'tracked' => $tracked,
                    ],
                ];
            });

        $totalQuantity = $variants->sum(fn ($v) => $v['variant_inventory']['quantity'] ?? 0);
        $trackingInventory = $variants->contains(fn ($v) => $v['variant_inventory']['tracked']);

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
                'in_stock_count' => $variants->filter(fn ($v) => $v['variant_inventory']['in_stock'])->count(),
                'out_of_stock_count' => $variants->filter(fn ($v) => ! $v['variant_inventory']['in_stock'] && $v['variant_inventory']['tracked'])->count(),
            ],
        ]);
    }

    /**
     * Bulk update inventory for multiple variants
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        if ($response = $this->ensureInventoryAddon($store)) {
            return $response;
        }

        $ledger = app(InventoryLedgerService::class);

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

                if (! $variant) {
                    $errors[] = [
                        'variant_id' => $variantData['variant_id'],
                        'error' => 'Variant not found or not accessible',
                    ];

                    continue;
                }

                $ledger->recordManualSetQuantity(
                    $store,
                    $variant,
                    (int) $variantData['quantity'],
                    'bulk_update',
                    $request->user()?->id
                );

                $variant->refresh();

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

    /**
     * @return array{id: int, sku: string|null, variant_inventory: array<string, mixed>}
     */
    protected function variantInventoryPayload(ProductVariant $variant): array
    {
        $ledger = app(InventoryLedgerService::class);
        $tracked = $ledger->isVariantTracked($variant);

        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'variant_inventory' => [
                'quantity' => $variant->inventory_quantity,
                'in_stock' => $variant->in_stock,
                'policy' => $variant->inventory_policy,
                'management' => $variant->inventory_management,
                'tracked' => $tracked,
            ],
        ];
    }
}
