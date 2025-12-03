<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
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

