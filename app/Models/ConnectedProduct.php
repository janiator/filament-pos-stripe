<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'compare_at_price_amount',
        'article_group_code',
        'vat_percent',
        'product_code',
        'quantity_unit_id',
        'vendor_id',
        'no_price_in_pos',
    ];

    protected $casts = [
        'active' => 'boolean',
        'images' => 'array',
        'product_meta' => 'array',
        'package_dimensions' => 'array',
        'shippable' => 'boolean',
        'compare_at_price_amount' => 'integer',
        'vat_percent' => 'decimal:2',
        'no_price_in_pos' => 'boolean',
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
        // Auto-set unit_label from quantity_unit when saving
        static::saving(function (ConnectedProduct $product) {
            if ($product->quantity_unit_id && !$product->unit_label) {
                $quantityUnit = QuantityUnit::find($product->quantity_unit_id);
                if ($quantityUnit && $quantityUnit->symbol) {
                    $product->unit_label = $quantityUnit->symbol;
                }
            }
        });

        static::saved(function (ConnectedProduct $product) {
            // For variable products: Only sync product details, not prices (variants handle pricing)
            // For single products: Sync both product details and prices
            
            // Sync price if price or currency changed
            // Only sync prices for single products
            if (!$product->isVariable() 
                && !$product->wasRecentlyCreated 
                && ($product->wasChanged('price') || $product->wasChanged('currency'))
                && $product->price 
                && $product->stripe_product_id 
                && $product->stripe_account_id) {
                $syncPriceAction = new \App\Actions\ConnectedPrices\SyncProductPrice();
                $syncPriceAction($product);
            }
            
            // Use saved event to ensure it fires for both create and update
            // Sync product details for both single and variable products (but not prices for variable)
            if (!$product->wasRecentlyCreated) {
                $listener = new \App\Listeners\SyncConnectedProductToStripeListener();
                $listener->handle($product);
            }
        });
        
        // Handle product deletion - archive in Stripe and delete/archive variants
        static::deleting(function (ConnectedProduct $product) {
            // Capture data before deletion for queued jobs
            $stripeProductId = $product->stripe_product_id;
            $stripeAccountId = $product->stripe_account_id;
            $productId = $product->id;
            $productName = $product->name;
            
            // First, handle all variants
            $variants = $product->variants()->get();
            foreach ($variants as $variant) {
                $variantHasBeenUsed = $variant->hasBeenUsedInPurchases();
                
                if ($variantHasBeenUsed) {
                    // Archive variant instead of deleting
                    $variant->active = false;
                    $variant->saveQuietly();
                    
                    // Queue job to archive variant product in Stripe
                    if ($variant->stripe_product_id && $variant->stripe_account_id) {
                        \App\Jobs\DeleteVariantProductFromStripeJob::dispatch(
                            $variant->stripe_product_id,
                            $variant->stripe_account_id,
                            $variant->id
                        );
                    }
                } else {
                    // Delete the variant - this will trigger the ProductVariant::deleting event
                    // which will queue archiving the variant product in Stripe
                    $variant->delete();
                }
            }
            
            // Queue job to archive product in Stripe (always archive, never delete from Stripe)
            // Products that have been used cannot be deleted in Stripe, only archived
            if ($stripeProductId && $stripeAccountId) {
                \App\Jobs\DeleteConnectedProductFromStripeJob::dispatch(
                    $stripeProductId,
                    $stripeAccountId,
                    $productId,
                    $productName
                );
            }
            
            // Note: Actual deletion prevention is handled in EditConnectedProduct page
            // via DeleteAction::before() callback
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
     * Get the variants for this product
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'connected_product_id')
            ->where('stripe_account_id', $this->stripe_account_id);
    }

    /**
     * Get the collections this product belongs to
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(
            Collection::class,
            'collection_product',
            'connected_product_id',
            'collection_id'
        )->withPivot('sort_order')
          ->withTimestamps()
          ->orderByPivot('sort_order');
    }

    /**
     * Get the quantity unit for this product
     */
    public function quantityUnit(): BelongsTo
    {
        return $this->belongsTo(QuantityUnit::class);
    }

    /**
     * Get the vendor for this product
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the article group code for this product
     * Note: Uses the code string, not a foreign key
     */
    public function articleGroupCode(): BelongsTo
    {
        return $this->belongsTo(ArticleGroupCode::class, 'article_group_code', 'code');
    }

    /**
     * Check if this is a variable product (has 2+ variants)
     * Variable products: Main product should NOT be created in Stripe, only variants
     * Single products: Only main product should be created in Stripe, no variants
     * 
     * Can be manually set via product_meta['product_type'] = 'variable' or 'single'
     * If 'auto', it will be detected from variant count
     */
    public function isVariable(): bool
    {
        // Check if manually set in metadata
        $meta = $this->product_meta ?? [];
        if (isset($meta['product_type'])) {
            return $meta['product_type'] === 'variable';
        }
        
        // Auto-detect: Variable products must have 2 or more variants
        // Single products have 0 or 1 variant
        return $this->variants()->count() >= 2;
    }

    /**
     * Check if this is a single product (0 or 1 variant)
     */
    public function isSingle(): bool
    {
        // Check if manually set in metadata
        $meta = $this->product_meta ?? [];
        if (isset($meta['product_type'])) {
            return $meta['product_type'] === 'single';
        }
        
        return !$this->isVariable();
    }

    /**
     * Check if this product has been used in any purchases
     * (subscriptions, payment links, etc.)
     */
    public function hasBeenUsedInPurchases(): bool
    {
        // Check if any prices from this product are used in subscription items
        $prices = $this->prices()->pluck('stripe_price_id');
        if ($prices->isNotEmpty()) {
            $usedInSubscriptions = \App\Models\ConnectedSubscriptionItem::whereIn('connected_price', $prices)
                ->exists();
            
            if ($usedInSubscriptions) {
                return true;
            }
            
            // Check if any prices are used in payment links
            $usedInPaymentLinks = \App\Models\ConnectedPaymentLink::whereIn('stripe_price_id', $prices)
                ->exists();
            
            if ($usedInPaymentLinks) {
                return true;
            }
        }
        
        // Check if any variant prices are used
        foreach ($this->variants as $variant) {
            if ($variant->hasBeenUsedInPurchases()) {
                return true;
            }
        }
        
        return false;
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

    /**
     * Get discount percentage if compare_at_price is set
     */
    public function getDiscountPercentageAttribute(): ?float
    {
        if (!$this->compare_at_price_amount) {
            return null;
        }

        // Get current price amount
        $priceAmount = null;
        if ($this->price) {
            $priceAmount = (int) round((float) str_replace(',', '.', $this->price) * 100);
        } elseif ($this->default_price && $this->stripe_account_id) {
            $defaultPrice = ConnectedPrice::where('stripe_price_id', $this->default_price)
                ->where('stripe_account_id', $this->stripe_account_id)
                ->first();
            
            if ($defaultPrice && $defaultPrice->unit_amount) {
                $priceAmount = $defaultPrice->unit_amount;
            }
        }

        if (!$priceAmount || $priceAmount <= 0) {
            return null;
        }

        if ($this->compare_at_price_amount <= $priceAmount) {
            return null;
        }

        return round((($this->compare_at_price_amount - $priceAmount) / $this->compare_at_price_amount) * 100, 1);
    }
}
