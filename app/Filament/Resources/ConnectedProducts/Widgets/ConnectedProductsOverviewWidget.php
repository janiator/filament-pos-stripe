<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConnectedProducts\Widgets;

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\ConnectedProduct;
use App\Models\ProductVariant;
use App\Models\Store;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ConnectedProductsOverviewWidget extends BaseWidget
{
    protected ?string $heading = null;

    protected ?string $description = null;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var int|array<string, ?int>|null
     */
    protected int|array|null $columns = 3;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        $store = $tenant instanceof Store ? $tenant : null;

        $productQuery = ConnectedProduct::query();
        $productQuery = $this->applyTenantScope($productQuery, $store);

        $totalProducts = (clone $productQuery)->count();

        $inventoryAddon = $store instanceof Store
            && Addon::storeHasActiveAddon($store->id, AddonType::Inventory);

        $inventoryUnits = 0;
        if ($inventoryAddon) {
            $inventoryQuery = ProductVariant::query()
                ->whereHas('product', function (Builder $query) use ($store): void {
                    $this->applyTenantScope($query, $store);
                });
            $inventoryUnits = (int) $inventoryQuery->sum('inventory_quantity');
        }

        $priceValues = (clone $productQuery)
            ->whereNotNull('price')
            ->where('price', '!=', '')
            ->pluck('price');

        $avgPrice = $priceValues
            ->map(fn (mixed $p): float => (float) str_replace([' ', ','], ['', '.'], (string) $p))
            ->filter(fn (float $v): bool => $v > 0)
            ->avg();

        $avgPriceFormatted = $avgPrice !== null
            ? number_format((float) $avgPrice, 2, '.', '')
            : '—';

        $stats = [
            Stat::make(__('filament.connected_products.stats.total_products'), (string) $totalProducts),
        ];

        if ($inventoryAddon) {
            $stats[] = Stat::make(__('filament.connected_products.stats.inventory_units'), (string) $inventoryUnits);
        }

        $stats[] = Stat::make(__('filament.connected_products.stats.average_price'), $avgPriceFormatted);

        return $stats;
    }

    protected function applyTenantScope(Builder $query, ?Store $tenant): Builder
    {
        if ($tenant instanceof Store && $tenant->slug !== 'visivo-admin') {
            $query->whereHas('store', function (Builder $q) use ($tenant): void {
                $q->where('stores.id', $tenant->id);
            });
        }

        return $query;
    }
}
