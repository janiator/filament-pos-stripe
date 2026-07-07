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

    /**
     * Get the store that owns this quantity unit
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the products using this quantity unit
     */
    public function products(): HasMany
    {
        return $this->hasMany(ConnectedProduct::class);
    }

    /**
     * Get display name (name with symbol)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name.($this->symbol ? ' ('.$this->symbol.')' : '');
    }

    /**
     * Global standard units available in product/API selects.
     * Optionally include a legacy or orphaned unit so existing values stay visible while editing.
     */
    public function scopeForSelect(Builder $query, ?int $includeId = null): Builder
    {
        return $query->where(function (Builder $q) use ($includeId): void {
            $q->where(function (Builder $global): void {
                $global->whereNull('store_id')
                    ->whereNull('stripe_account_id')
                    ->where('active', true);
            });

            if ($includeId !== null) {
                $q->orWhere('id', $includeId);
            }
        })->orderBy('name');
    }

    public static function defaultPiece(): ?self
    {
        return static::query()
            ->whereNull('store_id')
            ->whereNull('stripe_account_id')
            ->where('active', true)
            ->where('name', 'Piece')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    public static function optionsForSelect(?int $includeId = null): array
    {
        return static::query()
            ->forSelect($includeId)
            ->get()
            ->mapWithKeys(fn (self $unit): array => [(string) $unit->id => $unit->display_name])
            ->all();
    }

    public static function selectableGlobalIds(): array
    {
        return static::query()
            ->whereNull('store_id')
            ->whereNull('stripe_account_id')
            ->where('active', true)
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->all();
    }

    public static function isSelectableGlobalId(?int $id): bool
    {
        if ($id === null) {
            return false;
        }

        return static::query()
            ->whereKey($id)
            ->whereNull('store_id')
            ->whereNull('stripe_account_id')
            ->where('active', true)
            ->exists();
    }

    public static function resolveReplacementId(?int $unitId): ?int
    {
        if ($unitId !== null && static::isSelectableGlobalId($unitId)) {
            return $unitId;
        }

        $globals = static::query()
            ->whereNull('store_id')
            ->whereNull('stripe_account_id')
            ->where('active', true)
            ->get()
            ->keyBy(fn (self $unit): string => strtolower($unit->name.'|'.($unit->symbol ?? '')));

        $defaultPieceId = $globals->first(
            fn (self $unit): bool => $unit->name === 'Piece' && $unit->symbol === 'stk'
        )?->id ?? $globals->first()?->id;

        if ($unitId === null) {
            return $defaultPieceId;
        }

        $existing = static::query()->find($unitId);

        if ($existing === null) {
            return $defaultPieceId;
        }

        $key = strtolower($existing->name.'|'.($existing->symbol ?? ''));

        return $globals->get($key)?->id ?? $defaultPieceId;
    }

    public static function labelForId(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $unit = static::query()->find($id);

        return $unit?->display_name;
    }

    /**
     * Ensure global units exist and point every product at a selectable global unit.
     */
    public static function remapLegacyProductReferences(): int
    {
        (new \Database\Seeders\QuantityUnitSeeder)->run();

        $globals = static::query()
            ->whereNull('store_id')
            ->whereNull('stripe_account_id')
            ->where('active', true)
            ->get()
            ->keyBy(fn (self $unit): string => strtolower($unit->name.'|'.($unit->symbol ?? '')));

        $defaultPieceId = static::resolveReplacementId(null);

        if ($defaultPieceId === null) {
            return 0;
        }

        $selectableIds = $globals->pluck('id')->all();
        $updated = 0;

        DB::table('connected_products')
            ->whereNotNull('quantity_unit_id')
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$updated, $selectableIds, $defaultPieceId): void {
                foreach ($products as $product) {
                    $currentId = (int) $product->quantity_unit_id;

                    if (in_array($currentId, $selectableIds, true)) {
                        continue;
                    }

                    $replacementId = static::resolveReplacementId($currentId) ?? $defaultPieceId;

                    DB::table('connected_products')
                        ->where('id', $product->id)
                        ->update(['quantity_unit_id' => $replacementId]);

                    $updated++;
                }
            });

        return $updated;
    }
}
