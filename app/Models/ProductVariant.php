<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'connected_product_id',
        'stripe_account_id',
        'stripe_product_id', // New: Each variant is a separate Stripe Product
        'stripe_price_id',
        'sku',
        'barcode',
        'option1_name',
        'option1_value',
        'option2_name',
        'option2_value',
        'option3_name',
        'option3_value',
        'price_amount',
        'currency',
        'compare_at_price_amount',
        'weight_grams',
        'requires_shipping',
        'taxable',
        'inventory_quantity',
        'inventory_policy',
        'inventory_management',
        'image_url',
        'metadata',
        'active',
        'no_price_in_pos',
    ];

    protected $casts = [
        'price_amount' => 'integer',
        'compare_at_price_amount' => 'integer',
        'weight_grams' => 'integer',
        'requires_shipping' => 'boolean',
        'taxable' => 'boolean',
        'inventory_quantity' => 'integer',
        'active' => 'boolean',
        'metadata' => 'array',
        'no_price_in_pos' => 'boolean',
    ];

    /**
     * Get the product this variant belongs to
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ConnectedProduct::class, 'connected_product_id');
    }

    /**
     * Get the price associated with this variant
     */
    public function price(): ?BelongsTo
    {
        if (!$this->stripe_price_id) {
            return null;
        }

        return $this->belongsTo(
            ConnectedPrice::class,
            'stripe_price_id',
            'stripe_price_id'
        )->where('stripe_account_id', $this->stripe_account_id);
    }

    /**
     * Get the store that owns this variant
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        if (!$this->price_amount) {
            return 'N/A';
        }

        return number_format($this->price_amount / 100, 2) . ' ' . strtoupper($this->currency ?? 'NOK');
    }

    /**
     * Get variant name from options
     */
    public function getVariantNameAttribute(): string
    {
        $parts = [];
        
        if ($this->option1_value) {
            $parts[] = $this->option1_value;
        }
        if ($this->option2_value) {
            $parts[] = $this->option2_value;
        }
        if ($this->option3_value) {
            $parts[] = $this->option3_value;
        }
        
        return implode(' / ', $parts) ?: 'Default';
    }

    /**
     * Get full variant title (Product Name - Variant Name)
     */
    public function getFullTitleAttribute(): string
    {
        $productName = $this->product?->name ?? 'Product';
        $variantName = $this->variant_name;
        
        if ($variantName === 'Default') {
            return $productName;
        }
        
        return "{$productName} - {$variantName}";
    }

    /**
     * Check if variant is in stock
     */
    public function getInStockAttribute(): bool
    {
        if ($this->inventory_quantity === null) {
            return true; // Not tracking inventory
        }

        if ($this->inventory_policy === 'continue') {
            return true; // Allow backorders
        }

        return $this->inventory_quantity > 0;
    }

    /**
     * Get discount percentage if compare_at_price is set
     */
    public function getDiscountPercentageAttribute(): ?float
    {
        if (!$this->compare_at_price_amount || !$this->price_amount) {
            return null;
        }

        if ($this->compare_at_price_amount <= $this->price_amount) {
            return null;
        }

        return round((($this->compare_at_price_amount - $this->price_amount) / $this->compare_at_price_amount) * 100, 1);
    }

    /**
     * Get applicable automatic discount
     */
    public function getApplicableDiscount(?string $customerId = null, int $quantity = 1, int $cartTotal = 0): ?\App\Models\Discount
    {
        $discountService = app(\App\Services\DiscountService::class);
        return $discountService->getBestDiscount($this, $customerId, $quantity, $cartTotal);
    }

    /**
     * Get discounted price information
     */
    public function getDiscountedPrice(?string $customerId = null, int $quantity = 1, int $cartTotal = 0): array
    {
        $discountService = app(\App\Services\DiscountService::class);
        return $discountService->calculateDiscountedPrice($this, null, $customerId, $quantity, $cartTotal);
    }

    /**
     * Check if this variant has been used in any purchases
     * (subscriptions, payment links, etc.)
     */
    public function hasBeenUsedInPurchases(): bool
    {
        if (!$this->stripe_price_id) {
            return false;
        }
        
        // Check if price is used in subscription items
        $usedInSubscriptions = \App\Models\ConnectedSubscriptionItem::where('connected_price', $this->stripe_price_id)
            ->exists();
        
        if ($usedInSubscriptions) {
            return true;
        }
        
        // Check if price is used in payment links
        $usedInPaymentLinks = \App\Models\ConnectedPaymentLink::where('stripe_price_id', $this->stripe_price_id)
            ->exists();
        
        return $usedInPaymentLinks;
    }

    /**
     * Boot the model and set up event listeners
     */
    protected static function booted(): void
    {
        // Create Stripe product when variant is created
        // Only create in Stripe for variable products (where parent product has 2+ variants)
        static::created(function (ProductVariant $variant) {
            // Check if parent product is variable - only variable products should have variants in Stripe
            // A product is variable if it has 2+ variants (this variant + existing ones)
            $product = $variant->product;
            if (!$product) {
                return;
            }

            // Count existing variants (including this one that was just created)
            $variantCount = $product->variants()->count();
            
            // Only create variant in Stripe if product is variable (2+ variants)
            if ($variantCount < 2) {
                \Illuminate\Support\Facades\Log::info('Skipping Stripe creation for variant - parent product is not variable', [
                    'variant_id' => $variant->id,
                    'product_id' => $product->id,
                    'variant_count' => $variantCount,
                ]);
                return;
            }

            // Skip Stripe sync for variants without prices (custom price input on POS)
            if (!$variant->price_amount || $variant->price_amount <= 0) {
                \Illuminate\Support\Facades\Log::info('Skipping Stripe creation for variant - no price set (custom price on POS)', [
                    'variant_id' => $variant->id,
                    'product_id' => $product->id,
                ]);
                return;
            }

            if (!$variant->stripe_product_id && $variant->stripe_account_id) {
                $createVariantProductAction = app(\App\Actions\ConnectedProducts\CreateVariantProductInStripe::class);
                $stripeProductId = $createVariantProductAction($variant);

                if ($stripeProductId) {
                    $variant->stripe_product_id = $stripeProductId;
                    $variant->saveQuietly();

                    // Create price for the variant product (skip if no_price_in_pos is enabled)
                    if (!$variant->no_price_in_pos && $variant->price_amount && $variant->price_amount > 0) {
                        $createPriceAction = app(\App\Actions\ConnectedPrices\CreateConnectedPriceInStripe::class);
                        $priceId = $createPriceAction(
                            $stripeProductId,
                            $variant->stripe_account_id,
                            $variant->price_amount,
                            $variant->currency ?? 'nok',
                            [
                                'nickname' => $variant->variant_name,
                                'metadata' => [
                                    'source' => 'variant',
                                    'variant_id' => (string) $variant->id,
                                    'sku' => $variant->sku ?? '',
                                    'barcode' => $variant->barcode ?? '',
                                ],
                            ]
                        );

                        if ($priceId) {
                            $variant->stripe_price_id = $priceId;
                            $variant->saveQuietly();
                        }
                    } elseif ($variant->no_price_in_pos) {
                        \Illuminate\Support\Facades\Log::info('Skipping Stripe price creation for variant - no_price_in_pos is enabled', [
                            'variant_id' => $variant->id,
                            'product_id' => $variant->connected_product_id,
                        ]);
                    }
                }
            }
        });

        // Update Stripe product when variant is updated
        static::updated(function (ProductVariant $variant) {
            // Only sync if relevant fields changed
            $syncableFields = [
                'option1_name', 'option1_value',
                'option2_name', 'option2_value',
                'option3_name', 'option3_value',
                'price_amount', 'currency',
                'requires_shipping', 'taxable',
                'active', 'image_url',
            ];

            $hasChanges = false;
            foreach ($syncableFields as $field) {
                if ($variant->wasChanged($field)) {
                    $hasChanges = true;
                    break;
                }
            }

            if ($hasChanges && $variant->stripe_product_id && $variant->stripe_account_id) {
                // Dispatch job to update variant product in Stripe
                \App\Jobs\SyncVariantProductToStripeJob::dispatch($variant);
            }
        });

        // Archive variant product in Stripe when deleted
        static::deleting(function (ProductVariant $variant) {
            if ($variant->stripe_product_id && $variant->stripe_account_id) {
                // Queue job to archive product in Stripe (can't delete if used)
                \App\Jobs\DeleteVariantProductFromStripeJob::dispatch(
                    $variant->stripe_product_id,
                    $variant->stripe_account_id,
                    $variant->id
                );
            }
        });
    }
}

