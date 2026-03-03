<?php

namespace App\Models;

use App\Enums\AddonType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Addon extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AddonType::class,
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Whether the given store has an active add-on of the given type.
     */
    public static function storeHasActiveAddon(?int $storeId, AddonType $type): bool
    {
        if (! $storeId) {
            return false;
        }

        return self::query()
            ->where('store_id', $storeId)
            ->where('type', $type)
            ->where('is_active', true)
            ->exists();
    }
}
