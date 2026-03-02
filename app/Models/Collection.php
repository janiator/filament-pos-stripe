<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'parent_id',
        'stripe_account_id',
        'name',
        'description',
        'handle',
        'image_url',
        'active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the store that owns this collection
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the parent collection (for nested/hierarchy)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'parent_id');
    }

    /**
     * Get child collections
     */
    public function children(): HasMany
    {
        return $this->hasMany(Collection::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope: root collections only (no parent)
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get all descendant collection IDs (for cycle prevention in forms/API)
     */
    public static function descendantIds(int $collectionId): array
    {
        $collection = self::with('children')->find($collectionId);
        if (! $collection) {
            return [];
        }
        $ids = [];
        foreach ($collection->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, self::descendantIds($child->id));
        }
        return $ids;
    }

    /**
     * Get the products in this collection
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            ConnectedProduct::class,
            'collection_product',
            'collection_id',
            'connected_product_id'
        )->withPivot('sort_order')
          ->withTimestamps()
          ->orderByPivot('sort_order');
    }

    /**
     * Get products query without pivot ordering (for use in AttachAction)
     * This avoids DISTINCT/JSON column issues in PostgreSQL
     */
    public function productsForSelection(): BelongsToMany
    {
        return $this->belongsToMany(
            ConnectedProduct::class,
            'collection_product',
            'collection_id',
            'connected_product_id'
        )->withPivot('sort_order')
          ->withTimestamps();
    }

    /**
     * Get products count
     */
    public function getProductsCountAttribute(): int
    {
        return $this->products()->count();
    }
}

