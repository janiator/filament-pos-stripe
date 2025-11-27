<?php

namespace App\Jobs;

use App\Actions\ConnectedProducts\CreateConnectedProductInStripe;
use App\Actions\ConnectedPrices\CreateConnectedPriceInStripe;
use App\Actions\ConnectedProducts\CreateVariantProductInStripe;
use App\Actions\ConnectedProducts\UploadProductImagesToStripe;
use App\Models\ConnectedProduct;
use App\Models\ProductVariant;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportShopifyProductJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $productData,
        public string $stripeAccountId
    ) {
        $this->onQueue('imports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Check if product already exists (by name)
            $existing = ConnectedProduct::where('name', $this->productData['title'])
                ->where('stripe_account_id', $this->stripeAccountId)
                ->first();

            if ($existing) {
                Log::info('Product already exists, skipping', [
                    'name' => $this->productData['title'],
                    'stripe_account_id' => $this->stripeAccountId,
                ]);
                return;
            }

            // Create product
            $product = new ConnectedProduct([
                'stripe_account_id' => $this->stripeAccountId,
                'name' => $this->productData['title'],
                'description' => $this->stripHtml($this->productData['body_html'] ?? ''),
                'active' => $this->productData['published'] ?? false,
                'type' => 'good', // Physical product
                'shippable' => true,
                'product_meta' => [
                    'source' => 'shopify',
                    'handle' => $this->productData['handle'] ?? '',
                    'vendor' => $this->productData['vendor'] ?? '',
                    'type' => $this->productData['type'] ?? '',
                    'tags' => $this->productData['tags'] ?? '',
                ],
            ]);

            // Create product in Stripe first
            $createAction = new CreateConnectedProductInStripe();
            $stripeProductId = $createAction($product);

            if (!$stripeProductId) {
                Log::error('Failed to create product in Stripe', [
                    'name' => $this->productData['title'],
                ]);
                return;
            }

            $product->stripe_product_id = $stripeProductId;
            $product->save();

            // Download and add images
            if (!empty($this->productData['images'])) {
                $this->downloadAndAddImages($product, $this->productData['images']);
            }

            // Upload images to Stripe File API and get URLs
            if ($product->hasMedia('images')) {
                $uploadAction = new UploadProductImagesToStripe();
                $imageUrls = $uploadAction($product);
                if (!empty($imageUrls)) {
                    $product->images = $imageUrls;
                    $product->saveQuietly();
                }
            }

            // Create prices and variants
            $variants = $this->productData['variants'] ?? [];
            if (!empty($variants)) {
                $firstPriceId = null;
                foreach ($variants as $variantData) {
                    if (empty($variantData['price'])) {
                        continue;
                    }

                    $priceAmount = $this->parsePrice($variantData['price']);
                    if ($priceAmount <= 0) {
                        continue;
                    }

                    // Normalize SKU - convert empty string to null
                    $sku = !empty(trim($variantData['sku'] ?? '')) ? trim($variantData['sku']) : null;
                    
                    // Check if variant with same product, account, and options already exists
                    $existingVariant = ProductVariant::where('connected_product_id', $product->id)
                        ->where('stripe_account_id', $this->stripeAccountId)
                        ->where('option1_value', $variantData['option1_value'] ?? null)
                        ->where('option2_value', $variantData['option2_value'] ?? null)
                        ->where('option3_value', $variantData['option3_value'] ?? null)
                        ->first();
                    
                    // Create or update variant record
                    if ($existingVariant) {
                        $variant = $existingVariant;
                        $variant->fill([
                            'sku' => $sku,
                            'barcode' => $variantData['barcode'] ?? null,
                            'option1_name' => $variantData['option1_name'] ?? null,
                            'option1_value' => $variantData['option1_value'] ?? null,
                            'option2_name' => $variantData['option2_name'] ?? null,
                            'option2_value' => $variantData['option2_value'] ?? null,
                            'option3_name' => $variantData['option3_name'] ?? null,
                            'option3_value' => $variantData['option3_value'] ?? null,
                            'price_amount' => $priceAmount,
                            'currency' => 'nok',
                            'compare_at_price_amount' => !empty($variantData['compare_at_price']) 
                                ? $this->parsePrice($variantData['compare_at_price']) 
                                : null,
                            'weight_grams' => $variantData['grams'] ?? null,
                            'requires_shipping' => $variantData['requires_shipping'] ?? true,
                            'taxable' => $variantData['taxable'] ?? true,
                            'image_url' => $variantData['image'] ?? null,
                            'inventory_quantity' => $variantData['inventory_quantity'] ?? 0,
                            'inventory_policy' => $variantData['inventory_policy'] ?? 'deny',
                            'active' => true,
                            'metadata' => [
                                'source' => 'shopify',
                            ],
                        ]);
                        $variant->saveQuietly();

                        // Download and upload variant image to Stripe if it has one
                        if (!empty($variantData['image']) && filter_var($variantData['image'], FILTER_VALIDATE_URL)) {
                            $stripeImageUrl = $this->uploadVariantImageToStripe($variantData['image'], $this->stripeAccountId);
                            if ($stripeImageUrl) {
                                $variant->image_url = $stripeImageUrl;
                                $variant->saveQuietly();
                            }
                        }
                    } else {
                        $variant = ProductVariant::updateOrCreate(
                            [
                                'connected_product_id' => $product->id,
                                'stripe_account_id' => $this->stripeAccountId,
                                'sku' => $sku,
                            ],
                            [
                                'barcode' => $variantData['barcode'] ?? null,
                                'option1_name' => $variantData['option1_name'] ?? null,
                                'option1_value' => $variantData['option1_value'] ?? null,
                                'option2_name' => $variantData['option2_name'] ?? null,
                                'option2_value' => $variantData['option2_value'] ?? null,
                                'option3_name' => $variantData['option3_name'] ?? null,
                                'option3_value' => $variantData['option3_value'] ?? null,
                                'price_amount' => $priceAmount,
                                'currency' => 'nok',
                                'compare_at_price_amount' => !empty($variantData['compare_at_price']) 
                                    ? $this->parsePrice($variantData['compare_at_price']) 
                                    : null,
                                'weight_grams' => $variantData['grams'] ?? null,
                                'requires_shipping' => $variantData['requires_shipping'] ?? true,
                                'taxable' => $variantData['taxable'] ?? true,
                                'image_url' => $variantData['image'] ?? null,
                                'inventory_quantity' => $variantData['inventory_quantity'] ?? 0,
                                'inventory_policy' => $variantData['inventory_policy'] ?? 'deny',
                                'active' => true,
                                'metadata' => [
                                    'source' => 'shopify',
                                ],
                            ]
                        );
                    }

                    // Download and upload variant image to Stripe if it has one
                    if (!empty($variantData['image']) && filter_var($variantData['image'], FILTER_VALIDATE_URL)) {
                        $stripeImageUrl = $this->uploadVariantImageToStripe($variantData['image'], $this->stripeAccountId);
                        if ($stripeImageUrl) {
                            $variant->image_url = $stripeImageUrl;
                            $variant->saveQuietly();
                        }
                    }

                    // Create Stripe Product for this variant (if not already created)
                    if (!$variant->stripe_product_id) {
                        $createVariantProductAction = new CreateVariantProductInStripe();
                        $variantStripeProductId = $createVariantProductAction($variant);

                        if ($variantStripeProductId) {
                            $variant->stripe_product_id = $variantStripeProductId;
                            $variant->saveQuietly();
                        }
                    }

                    // Create Stripe Price for this variant product
                    if ($variant->stripe_product_id && !$variant->stripe_price_id) {
                        $createPriceAction = new CreateConnectedPriceInStripe();
                        $priceId = $createPriceAction(
                            $variant->stripe_product_id,
                            $this->stripeAccountId,
                            $priceAmount,
                            'nok',
                            [
                                'nickname' => $variant->variant_name,
                                'metadata' => [
                                    'source' => 'shopify-variant',
                                    'variant_id' => (string) $variant->id,
                                    'sku' => $variantData['sku'] ?? '',
                                    'barcode' => $variantData['barcode'] ?? '',
                                ],
                            ]
                        );

                        if ($priceId) {
                            if (!$firstPriceId) {
                                $firstPriceId = $priceId;
                            }
                            $variant->stripe_price_id = $priceId;
                            $variant->saveQuietly();
                        }
                    }
                }

                // Set first price as default if we have one
                if ($firstPriceId && empty($product->default_price)) {
                    $product->default_price = $firstPriceId;
                    $product->saveQuietly();
                }
            }

            Log::info('Successfully imported product', [
                'product_id' => $product->id,
                'name' => $product->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Error importing product', [
                'product' => $this->productData['title'] ?? 'Unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Download and add images to product
     */
    protected function downloadAndAddImages(ConnectedProduct $product, array $images): void
    {
        foreach ($images as $imageData) {
            try {
                $imageUrl = $imageData['src'] ?? null;
                if (!$imageUrl || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    continue;
                }

                $product->addMediaFromUrl($imageUrl)
                    ->toMediaCollection('images');
            } catch (\Exception $e) {
                Log::warning('Failed to download image', [
                    'url' => $imageData['src'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Parse price string to cents
     */
    protected function parsePrice(string $price): int
    {
        // Remove currency symbols and whitespace
        $price = preg_replace('/[^\d.,]/', '', $price);
        
        // Handle Norwegian format (1.234,56) or US format (1,234.56)
        if (strpos($price, ',') !== false && strpos($price, '.') !== false) {
            $lastComma = strrpos($price, ',');
            $lastDot = strrpos($price, '.');
            
            if ($lastComma > $lastDot) {
                // Norwegian format: 1.234,56
                $price = str_replace('.', '', $price);
                $price = str_replace(',', '.', $price);
            } else {
                // US format: 1,234.56
                $price = str_replace(',', '', $price);
            }
        } else {
            // Only one separator, assume it's decimal
            $price = str_replace(',', '.', $price);
        }
        
        // Convert to cents/øre
        return (int) round((float) $price * 100);
    }

    /**
     * Strip HTML tags and decode entities
     */
    protected function stripHtml(?string $html): ?string
    {
        if (!$html) {
            return null;
        }

        // Decode HTML entities
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Strip HTML tags
        $text = strip_tags($text);
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text ?: null;
    }

    /**
     * Upload variant image to Stripe and return the file link URL
     */
    protected function uploadVariantImageToStripe(string $imageUrl, string $stripeAccountId): ?string
    {
        try {
            $secret = config('cashier.secret') ?? config('services.stripe.secret');
            if (!$secret) {
                Log::warning('Stripe secret key not configured for variant image upload');
                return null;
            }

            $stripe = new \Stripe\StripeClient($secret);

            // Download the image to a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'variant_image_');
            if (!$tempFile) {
                Log::warning('Failed to create temporary file for variant image', [
                    'image_url' => $imageUrl,
                ]);
                return null;
            }

            try {
                // Download the image
                $imageContent = file_get_contents($imageUrl);
                if ($imageContent === false) {
                    Log::warning('Failed to download variant image', [
                        'image_url' => $imageUrl,
                    ]);
                    return null;
                }

                file_put_contents($tempFile, $imageContent);

                // Upload to Stripe File API
                $fileHandle = fopen($tempFile, 'rb');
                if (!$fileHandle) {
                    Log::warning('Failed to open temporary file for Stripe upload', [
                        'image_url' => $imageUrl,
                    ]);
                    return null;
                }

                try {
                    $file = $stripe->files->create([
                        'purpose' => 'product_image',
                        'file' => $fileHandle,
                    ], [
                        'stripe_account' => $stripeAccountId,
                    ]);

                    if (!isset($file->id)) {
                        Log::error('Stripe file upload did not return file ID', [
                            'image_url' => $imageUrl,
                            'file_response' => json_encode($file),
                        ]);
                        return null;
                    }

                    // Create a file link for the uploaded file
                    $fileLink = $stripe->fileLinks->create([
                        'file' => $file->id,
                    ], [
                        'stripe_account' => $stripeAccountId,
                    ]);

                    if (!isset($fileLink->url)) {
                        Log::error('Stripe file link creation did not return URL', [
                            'image_url' => $imageUrl,
                            'file_id' => $file->id,
                        ]);
                        return null;
                    }

                    return $fileLink->url;
                } finally {
                    fclose($fileHandle);
                }
            } finally {
                // Clean up temporary file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to upload variant image to Stripe', [
                'image_url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

