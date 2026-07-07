<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class QuantityUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'stripe_account_id',
        'name',
        'symbol',
        'description',
        'is_standard',
        'active',
    ];

    protected $casts = [
        'is_standard' => 'boolean',
        'active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(ConnectedProduct::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name.($this->symbol ? ' ('.$this->symbol.')' : '');
    }

    /**
     * Units shown in the catalog and product select: global rows plus optional store rows.
     */
    public function scopeVisibleInCatalog(Builder $query, ?int $storeId = null): Builder
    {
        $query->where('active', true);

        if ($storeId !== null) {
            $query->where(function (Builder $q) use ($storeId): void {
                $q->where(function (Builder $global): void {
                    $global->whereNull('store_id')->whereNull('stripe_account_id');
                })->orWhere('store_id', $storeId);
            });
        } else {
            $query->whereNull('store_id')->whereNull('stripe_account_id');
        }

        return $query->orderBy('name');
    }

    public static function defaultPieceId(?int $storeId = null): ?int
    {
        $query = static::query()->visibleInCatalog($storeId);

        return $query->clone()->where('name', 'Piece')->value('id')
            ?? $query->clone()->value('id');
    }

    /**
     * @return array<string, string>
     */
    public static function optionsForCatalog(?int $storeId = null): array
    {
        return static::query()
            ->visibleInCatalog($storeId)
            ->get()
            ->mapWithKeys(fn (self $unit): array => [(string) $unit->id => $unit->display_name])
            ->all();
    }

    public static function isVisibleCatalogId(?int $id, ?int $storeId = null): bool
    {
        if ($id === null) {
            return false;
        }

        return static::query()->visibleInCatalog($storeId)->whereKey($id)->exists();
    }

    /**
     * Map a legacy unit id to a catalog-visible id. Never returns null when $unitId is set.
     */
    public static function resolveReplacementId(?int $unitId, ?int $storeId = null): ?int
    {
        if ($unitId !== null && static::isVisibleCatalogId($unitId, $storeId)) {
            return $unitId;
        }

        $defaultId = static::defaultPieceId($storeId);

        if ($unitId === null) {
            return $defaultId;
        }

        $existing = static::query()->find($unitId);

        if ($existing === null) {
            return $defaultId ?? $unitId;
        }

        $globalMatch = static::query()
            ->whereNull('store_id')
            ->whereNull('stripe_account_id')
            ->where('active', true)
            ->where('name', $existing->name)
            ->where(function (Builder $query) use ($existing): void {
                if ($existing->symbol === null) {
                    $query->whereNull('symbol');
                } else {
                    $query->where('symbol', $existing->symbol);
                }
            })
            ->first();

        if ($globalMatch !== null) {
            return $globalMatch->id;
        }

        $match = static::query()
            ->visibleInCatalog($storeId)
            ->where('name', $existing->name)
            ->where(function (Builder $query) use ($existing): void {
                if ($existing->symbol === null) {
                    $query->whereNull('symbol');
                } else {
                    $query->where('symbol', $existing->symbol);
                }
            })
            ->first();

        return $match?->id ?? $defaultId ?? $unitId;
    }

    public static function resolveToGlobalId(?int $unitId): ?int
    {
        if ($unitId !== null && static::query()->whereKey($unitId)->whereNull('store_id')->whereNull('stripe_account_id')->where('active', true)->exists()) {
            return $unitId;
        }

        $defaultId = static::defaultPieceId();

        if ($unitId === null) {
            return $defaultId;
        }

        $existing = static::query()->find($unitId);

        if ($existing === null) {
            return $defaultId ?? $unitId;
        }

        $globalMatch = static::query()
            ->whereNull('store_id')
            ->whereNull('stripe_account_id')
            ->where('active', true)
            ->where('name', $existing->name)
            ->where(function (Builder $query) use ($existing): void {
                if ($existing->symbol === null) {
                    $query->whereNull('symbol');
                } else {
                    $query->where('symbol', $existing->symbol);
                }
            })
            ->first();

        return $globalMatch?->id ?? $defaultId ?? $unitId;
    }

    public static function defaultPiece(): ?self
    {
        $id = static::defaultPieceId();

        return $id ? static::query()->find($id) : null;
    }

    public static function labelForId(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $unit = static::query()->find($id);

        return $unit?->display_name;
    }

    public static function storeIdForStripeAccount(?string $stripeAccountId): ?int
    {
        if ($stripeAccountId === null || $stripeAccountId === '') {
            return null;
        }

        return Store::query()->where('stripe_account_id', $stripeAccountId)->value('id');
    }

    /**
     * Ensure global units exist and point every product at a catalog-visible unit.
     */
    public static function remapLegacyProductReferences(): int
    {
        (new \Database\Seeders\QuantityUnitSeeder)->run();

        $updated = 0;

        DB::table('connected_products')
            ->whereNotNull('quantity_unit_id')
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$updated): void {
                foreach ($products as $product) {
                    $currentId = (int) $product->quantity_unit_id;
                    $storeId = static::storeIdForStripeAccount($product->stripe_account_id ?? null);

                    $replacementId = static::resolveToGlobalId($currentId);

                    if ($replacementId === null || $replacementId === $currentId) {
                        continue;
                    }

                    DB::table('connected_products')
                        ->where('id', $product->id)
                        ->update(['quantity_unit_id' => $replacementId]);

                    $updated++;
                }
            });

        return $updated;
    }
}
