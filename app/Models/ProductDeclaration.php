<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ProductDeclaration extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'product_name',
        'vendor_name',
        'version',
        'version_identification',
        'declaration_date',
        'content',
        'is_active',
    ];

    protected $casts = [
        'declaration_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the store for this product declaration
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Resolve declaration_date for display/API formatting (Carbon instance or date string).
     */
    public function resolvedDeclarationDate(): ?CarbonInterface
    {
        $attributes = $this->getAttributes();

        if (array_key_exists('declaration_date', $attributes)) {
            $value = $attributes['declaration_date'];
        } else {
            $value = $this->getAttribute('declaration_date');
        }

        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    /**
     * Get the active product declaration for a store
     */
    public static function getActiveForStore(int $storeId): ?self
    {
        return static::where('store_id', $storeId)
            ->where('is_active', true)
            ->latest()
            ->first();
    }
}
