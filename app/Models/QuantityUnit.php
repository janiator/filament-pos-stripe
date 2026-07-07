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
     * @return array<int, string>
     */
    public static function optionsForSelect(?int $includeId = null): array
    {
        return static::query()
            ->forSelect($includeId)
            ->get()
            ->mapWithKeys(fn (self $unit): array => [$unit->id => $unit->display_name])
            ->all();
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
     * Point products at global units when they still reference deleted or per-store rows.
     */
    public static function remapLegacyProductReferences(): void
    {
        (new \Database\Seeders\QuantityUnitSeeder)->run();

        $globals = static::query()
            ->whereNull('store_id')
            ->whereNull('stripe_account_id')
            ->get()
            ->keyBy(fn (self $unit): string => strtolower($unit->name.'|'.($unit->symbol ?? '')));

        $defaultPieceId = $globals->first(
            fn (self $unit): bool => $unit->name === 'Piece' && $unit->symbol === 'stk'
        )?->id ?? $globals->first()?->id;

        if ($defaultPieceId === null) {
            return;
        }

        DB::table('connected_products')
            ->whereNotNull('quantity_unit_id')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('quantity_units')
                    ->whereColumn('quantity_units.id', 'connected_products.quantity_unit_id');
            })
            ->update(['quantity_unit_id' => $defaultPieceId]);

        foreach (static::query()->whereNotNull('store_id')->get() as $storeUnit) {
            $key = strtolower($storeUnit->name.'|'.($storeUnit->symbol ?? ''));
            $global = $globals->get($key);

            if (! $global) {
                continue;
            }

            DB::table('connected_products')
                ->where('quantity_unit_id', $storeUnit->id)
                ->update(['quantity_unit_id' => $global->id]);
        }
    }
}
