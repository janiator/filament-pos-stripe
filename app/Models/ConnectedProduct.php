<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectedProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_product_id',
        'stripe_account_id',
        'name',
        'description',
        'active',
        'images',
        'metadata',
        'type',
        'url',
    ];

    protected $casts = [
        'active' => 'boolean',
        'images' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (ConnectedProduct $product) {
            // Use saved event to ensure it fires for both create and update
            // Only sync on update (not create)
            if ($product->wasRecentlyCreated) {
                return;
            }
            
            $listener = new \App\Listeners\SyncConnectedProductToStripeListener();
            $listener->handle($product);
        });
    }

    /**
     * Get the store that owns this product via stripe_account_id
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the prices for this product
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ConnectedPrice::class, 'stripe_product_id', 'stripe_product_id')
            ->where('stripe_account_id', $this->stripe_account_id);
    }
}
