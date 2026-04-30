<?php

namespace App\Services;

use App\Enums\AddonType;
use App\Exceptions\InsufficientStockException;
use App\Models\Addon;
use App\Models\ConnectedCharge;
use App\Models\InventoryStockMovement;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryLedgerService
{
    public function inventoryAddonActive(Store $store): bool
    {
        return Addon::storeHasActiveAddon($store->id, AddonType::Inventory);
    }

    public function isVariantTracked(ProductVariant $variant, ?Store $store = null): bool
    {
        if ($variant->inventory_quantity === null) {
            return false;
        }

        if (! $variant->relationLoaded('product')) {
            $variant->load('product');
        }

        $product = $variant->product;
        if (! $product || ! $product->track_inventory) {
            return false;
        }

        $store ??= Store::query()->where('stripe_account_id', $variant->stripe_account_id)->first();

        if (! $store) {
            return false;
        }

        return Addon::storeHasActiveAddon($store->id, AddonType::Inventory);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items  Cart lines with optional variant_id, quantity
     *
     * @throws InsufficientStockException
     */
    public function assertCartSellable(Store $store, array $items): void
    {
        if (! $this->inventoryAddonActive($store)) {
            return;
        }

        $byVariant = $this->aggregateVariantQuantities($items);
        if ($byVariant === []) {
            return;
        }

        $variantIds = array_keys($byVariant);
        sort($variantIds);

        $failures = [];

        foreach ($variantIds as $variantId) {
            $qty = $byVariant[$variantId];
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::query()
                ->whereKey($variantId)
                ->where('stripe_account_id', $store->stripe_account_id)
                ->lockForUpdate()
                ->first();

            if (! $variant) {
                continue;
            }

            if (! $this->isVariantTracked($variant)) {
                continue;
            }

            if ($variant->inventory_policy === 'continue') {
                continue;
            }

            $available = $variant->inventory_quantity ?? 0;
            if ($available < $qty) {
                $failures[] = [
                    'variant_id' => (int) $variant->id,
                    'product_id' => $variant->connected_product_id,
                    'requested' => $qty,
                    'available' => $available,
                    'sku' => $variant->sku,
                ];
            }
        }

        if ($failures !== []) {
            throw new InsufficientStockException(
                'Insufficient stock for one or more items.',
                $failures
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     */
    public function applySaleForCharge(Store $store, array $cartItems, ConnectedCharge $charge): void
    {
        if (! $this->inventoryAddonActive($store)) {
            return;
        }

        $byVariant = $this->aggregateVariantQuantities($cartItems);
        if ($byVariant === []) {
            return;
        }

        foreach ($byVariant as $variantId => $qty) {
            $this->applySaleLine(
                $store,
                (int) $variantId,
                $qty,
                'sale:charge:'.$charge->id.':variant:'.$variantId,
                [
                    'reason' => InventoryStockMovement::REASON_SALE,
                    'connected_charge_id' => $charge->id,
                    'pos_event_id' => null,
                ]
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     */
    public function applySaleForSplitPosEvent(Store $store, array $cartItems, int $posEventId): void
    {
        if (! $this->inventoryAddonActive($store)) {
            return;
        }

        $byVariant = $this->aggregateVariantQuantities($cartItems);
        if ($byVariant === []) {
            return;
        }

        foreach ($byVariant as $variantId => $qty) {
            $this->applySaleLine(
                $store,
                (int) $variantId,
                $qty,
                'sale:pos_event:'.$posEventId.':variant:'.$variantId,
                [
                    'reason' => InventoryStockMovement::REASON_SALE,
                    'connected_charge_id' => null,
                    'pos_event_id' => $posEventId,
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $movementAttrs  reason, connected_charge_id, pos_event_id
     */
    protected function applySaleLine(
        Store $store,
        int $variantId,
        int $quantitySold,
        string $idempotencyKey,
        array $movementAttrs
    ): void {
        if ($quantitySold <= 0) {
            return;
        }

        DB::transaction(function () use ($store, $variantId, $quantitySold, $idempotencyKey, $movementAttrs): void {
            if (InventoryStockMovement::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                return;
            }

            $variant = ProductVariant::query()
                ->whereKey($variantId)
                ->where('stripe_account_id', $store->stripe_account_id)
                ->lockForUpdate()
                ->first();

            if (! $variant || ! $this->isVariantTracked($variant)) {
                return;
            }

            $newQty = max(0, ($variant->inventory_quantity ?? 0) - $quantitySold);
            $variant->update(['inventory_quantity' => $newQty]);

            try {
                InventoryStockMovement::query()->create([
                    'store_id' => $store->id,
                    'product_variant_id' => $variant->id,
                    'quantity_delta' => -$quantitySold,
                    'reason' => $movementAttrs['reason'],
                    'connected_charge_id' => $movementAttrs['connected_charge_id'],
                    'pos_event_id' => $movementAttrs['pos_event_id'],
                    'refund_reference' => null,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => null,
                ]);
            } catch (QueryException $e) {
                if ($this->isDuplicateKey($e)) {
                    return;
                }
                throw $e;
            }
        });
    }

    /**
     * Restore stock after a refund. Idempotent per charge + refund index + variant.
     *
     * @param  array<int, array<string, mixed>>|null  $refundedItems
     */
    public function applyRefund(
        Store $store,
        ConnectedCharge $charge,
        int $refundIndex,
        ?array $refundedItems,
        bool $isFullRefund
    ): void {
        if (! $this->inventoryAddonActive($store)) {
            return;
        }

        $quantities = $this->resolveRefundQuantitiesByVariant($charge, $refundedItems, $isFullRefund);
        if ($quantities === []) {
            return;
        }

        foreach ($quantities as $variantId => $qty) {
            if (! $this->hasSaleDeductionForVariant($charge, (int) $variantId)) {
                continue;
            }
            $idempotencyKey = 'refund:charge:'.$charge->id.':idx:'.$refundIndex.':variant:'.$variantId;
            $this->applyRefundLine($store, (int) $variantId, $qty, $idempotencyKey, $charge->id);
        }
    }

    public function hasSaleDeductionForVariant(ConnectedCharge $charge, int $variantId): bool
    {
        $meta = $charge->metadata ?? [];
        $posEventId = $meta['inventory_pos_event_id'] ?? null;
        if ($posEventId !== null && $posEventId !== '') {
            return InventoryStockMovement::query()
                ->where('pos_event_id', (int) $posEventId)
                ->where('product_variant_id', $variantId)
                ->where('reason', InventoryStockMovement::REASON_SALE)
                ->exists();
        }

        return InventoryStockMovement::query()
            ->where('connected_charge_id', $charge->id)
            ->where('product_variant_id', $variantId)
            ->where('reason', InventoryStockMovement::REASON_SALE)
            ->exists();
    }

    /**
     * @return array<int, int> variant_id => quantity to restore
     */
    protected function resolveRefundQuantitiesByVariant(
        ConnectedCharge $charge,
        ?array $refundedItems,
        bool $isFullRefund
    ): array {
        $metadata = $charge->metadata ?? [];
        $items = $metadata['items'] ?? [];

        if (is_array($refundedItems) && $refundedItems !== []) {
            return $this->mapRefundedItemsToVariants($items, $refundedItems);
        }

        $isSplitPrimary = (bool) ($metadata['inventory_split_primary'] ?? false);
        $hasSplitEvent = ! empty($metadata['inventory_pos_event_id']);
        if ($hasSplitEvent && ! $isSplitPrimary) {
            return [];
        }

        if ($isFullRefund && is_array($items) && $items !== []) {
            return $this->aggregateVariantQuantities($items);
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $originalItems
     * @param  array<int, array<string, mixed>>  $refundedItems
     * @return array<int, int>
     */
    protected function mapRefundedItemsToVariants(array $originalItems, array $refundedItems): array
    {
        $byVariant = [];

        foreach ($refundedItems as $refunded) {
            $qty = (int) ($refunded['quantity'] ?? 1);
            if ($qty <= 0) {
                continue;
            }

            if (! empty($refunded['variant_id'])) {
                $vid = (int) $refunded['variant_id'];
                if ($vid > 0) {
                    $byVariant[$vid] = ($byVariant[$vid] ?? 0) + $qty;
                }

                continue;
            }

            $itemId = $refunded['item_id'] ?? null;
            if ($itemId === null) {
                continue;
            }

            foreach ($originalItems as $idx => $item) {
                $lineId = $item['item_id'] ?? $item['line_id'] ?? $idx;
                if ((string) $lineId !== (string) $itemId) {
                    continue;
                }
                $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : null;
                if ($variantId) {
                    $byVariant[$variantId] = ($byVariant[$variantId] ?? 0) + $qty;
                }
                break;
            }
        }

        return $byVariant;
    }

    protected function applyRefundLine(
        Store $store,
        int $variantId,
        int $quantityToRestore,
        string $idempotencyKey,
        int $chargeId
    ): void {
        if ($quantityToRestore <= 0) {
            return;
        }

        DB::transaction(function () use ($store, $variantId, $quantityToRestore, $idempotencyKey, $chargeId): void {
            if (InventoryStockMovement::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                return;
            }

            $variant = ProductVariant::query()
                ->whereKey($variantId)
                ->where('stripe_account_id', $store->stripe_account_id)
                ->lockForUpdate()
                ->first();

            if (! $variant || ! $this->isVariantTracked($variant)) {
                return;
            }

            $newQty = ($variant->inventory_quantity ?? 0) + $quantityToRestore;
            $variant->update(['inventory_quantity' => $newQty]);

            try {
                InventoryStockMovement::query()->create([
                    'store_id' => $store->id,
                    'product_variant_id' => $variant->id,
                    'quantity_delta' => $quantityToRestore,
                    'reason' => InventoryStockMovement::REASON_REFUND,
                    'connected_charge_id' => $chargeId,
                    'pos_event_id' => null,
                    'refund_reference' => (string) $idempotencyKey,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => null,
                ]);
            } catch (QueryException $e) {
                if ($this->isDuplicateKey($e)) {
                    return;
                }
                throw $e;
            }
        });
    }

    /**
     * Manual quantity change from API (adjustment relative to current stock).
     */
    public function recordManualAdjustment(
        Store $store,
        ProductVariant $variant,
        int $quantityDelta,
        ?string $reason = null,
        ?int $userId = null
    ): void {
        if (! $this->inventoryAddonActive($store)) {
            return;
        }

        if ($quantityDelta === 0) {
            return;
        }

        $idempotencyKey = 'manual:'.Str::uuid()->toString();

        DB::transaction(function () use ($store, $variant, $quantityDelta, $idempotencyKey, $reason, $userId): void {
            $locked = ProductVariant::query()
                ->whereKey($variant->id)
                ->where('stripe_account_id', $store->stripe_account_id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return;
            }

            $current = $locked->inventory_quantity ?? 0;
            $newQty = max(0, $current + $quantityDelta);
            $locked->update(['inventory_quantity' => $newQty]);

            InventoryStockMovement::query()->create([
                'store_id' => $store->id,
                'product_variant_id' => $locked->id,
                'quantity_delta' => $newQty - $current,
                'reason' => InventoryStockMovement::REASON_MANUAL_ADJUST,
                'connected_charge_id' => null,
                'pos_event_id' => null,
                'refund_reference' => null,
                'idempotency_key' => $idempotencyKey,
                'metadata' => array_filter([
                    'note' => $reason,
                    'user_id' => $userId,
                ]),
            ]);
        });
    }

    /**
     * Set absolute quantity and record net delta as manual adjustment.
     */
    public function recordManualSetQuantity(
        Store $store,
        ProductVariant $variant,
        int $newQuantity,
        ?string $reason = null,
        ?int $userId = null
    ): void {
        if (! $this->inventoryAddonActive($store)) {
            return;
        }

        DB::transaction(function () use ($store, $variant, $newQuantity, $reason, $userId): void {
            $locked = ProductVariant::query()
                ->whereKey($variant->id)
                ->where('stripe_account_id', $store->stripe_account_id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return;
            }

            $old = $locked->inventory_quantity ?? 0;
            $delta = $newQuantity - $old;
            if ($delta === 0) {
                return;
            }

            $locked->update(['inventory_quantity' => $newQuantity]);

            InventoryStockMovement::query()->create([
                'store_id' => $store->id,
                'product_variant_id' => $locked->id,
                'quantity_delta' => $delta,
                'reason' => InventoryStockMovement::REASON_MANUAL_ADJUST,
                'connected_charge_id' => null,
                'pos_event_id' => null,
                'refund_reference' => null,
                'idempotency_key' => 'manual:set:'.Str::uuid()->toString(),
                'metadata' => array_filter([
                    'note' => $reason,
                    'user_id' => $userId,
                    'previous_quantity' => $old,
                ]),
            ]);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, int>
     */
    protected function aggregateVariantQuantities(array $items): array
    {
        $byVariant = [];

        foreach ($items as $item) {
            if (! isset($item['variant_id'])) {
                continue;
            }
            $vid = (int) $item['variant_id'];
            if ($vid <= 0) {
                continue;
            }
            $qty = (int) ($item['quantity'] ?? 1);
            if ($qty <= 0) {
                continue;
            }
            $byVariant[$vid] = ($byVariant[$vid] ?? 0) + $qty;
        }

        return $byVariant;
    }

    protected function isDuplicateKey(QueryException $e): bool
    {
        $code = $e->errorInfo[1] ?? null;

        return $code === 1062 || $code === 23505;
    }
}
