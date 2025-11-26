<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ConnectedProduct extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'stripe_product_id',
        'stripe_account_id',
        'name',
        'description',
        'active',
        'images',
        'product_meta',
        'type',
        'url',
        'package_dimensions',
        'shippable',
        'statement_descriptor',
        'tax_code',
        'unit_label',
        'default_price',
        'price',
        'currency',
        'article_group_code',
        'product_code',
    ];

    protected $casts = [
        'active' => 'boolean',
        'images' => 'array',
        'product_meta' => 'array',
        'package_dimensions' => 'array',
        'shippable' => 'boolean',
    ];

    /**
     * Get the current price from default_price if price field is empty
     * Returns numeric value (e.g., 299.00) for form compatibility
     */
    public function getPriceAttribute($value)
    {
        // If price is set, return it (convert comma to dot if needed)
        if ($value) {
            return str_replace(',', '.', (string) $value);
        }

        // Otherwise, try to get from default_price
        if ($this->default_price && $this->stripe_account_id) {
            $defaultPrice = ConnectedPrice::where('stripe_price_id', $this->default_price)
                ->where('stripe_account_id', $this->stripe_account_id)
                ->first();
            
            if ($defaultPrice && $defaultPrice->unit_amount) {
                // Return as decimal number (e.g., 299.00) for form compatibility
                return number_format($defaultPrice->unit_amount / 100, 2, '.', '');
            }
        }

        return $value;
    }

    /**
     * Get currency from default_price if currency field is empty
     */
    public function getCurrencyAttribute($value)
    {
        // If currency is set, return it
        if ($value) {
            return $value;
        }

        // Otherwise, try to get from default_price
        if ($this->default_price && $this->stripe_account_id) {
            $defaultPrice = ConnectedPrice::where('stripe_price_id', $this->default_price)
                ->where('stripe_account_id', $this->stripe_account_id)
                ->first();
            
            if ($defaultPrice && $defaultPrice->currency) {
                return $defaultPrice->currency;
            }
        }

        return $value ?: 'nok';
    }

    protected static function booted(): void
    {
        static::saved(function (ConnectedProduct $product) {
            // Sync price if it changed (only on update, create is handled in afterCreate hook)
            if (!$product->wasRecentlyCreated && ($product->wasChanged('price') || $product->wasChanged('currency'))) {
                if ($product->price && $product->stripe_product_id && $product->stripe_account_id) {
                    $syncPriceAction = new \App\Actions\ConnectedPrices\SyncProductPrice();
                    $syncPriceAction($product);
                }
            }
            
            // Use saved event to ensure it fires for both create and update
            // Only sync product details on update (not create)
            if (!$product->wasRecentlyCreated) {
                $listener = new \App\Listeners\SyncConnectedProductToStripeListener();
                $listener->handle($product);
            }
        });
        
        // Handle product deletion - archive in Stripe
        static::deleting(function (ConnectedProduct $product) {
            // Archive product in Stripe before local deletion
            // Products that have been used cannot be deleted in Stripe, only archived
            if ($product->stripe_product_id && $product->stripe_account_id) {
                $deleteAction = new \App\Actions\ConnectedProducts\DeleteConnectedProductFromStripe();
                $deleteAction($product);
            }
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

    /**
     * Register media collections for product images
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->singleFile(false)
            ->useDisk('public'); // Store images in public disk for easy access
    }

    /**
     * Get image URLs for Stripe sync
     */
    public function getStripeImageUrls(): array
    {
        return $this->getMedia('images')
            ->map(function (Media $media) {
                // Use current request URL if available, otherwise use default
                if (app()->runningInConsole() === false && request()->hasHeader('Host')) {
                    $scheme = request()->getScheme();
                    $host = request()->getHost();
                    $port = request()->getPort();
                    $baseUrl = $scheme . '://' . $host . ($port && $port != 80 && $port != 443 ? ':' . $port : '');
                    $path = $media->getPath();
                    $relativePath = str_replace(public_path('storage'), '', $path);
                    return $baseUrl . '/storage' . $relativePath;
                }
                return $media->getUrl();
            })
            ->toArray();
    }
}
