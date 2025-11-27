<?php

namespace App\Services;

use App\Actions\ConnectedProducts\CreateConnectedProductInStripe;
use App\Actions\ConnectedPrices\CreateConnectedPriceInStripe;
use App\Actions\ConnectedProducts\UploadProductImagesToStripe;
use App\Models\ConnectedProduct;
use App\Models\ConnectedPrice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyCsvImporter
{
    /**
     * Parse CSV file and return structured data
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("CSV file not found: {$filePath}");
        }

        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            throw new \Exception("Could not open CSV file: {$filePath}");
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers || empty($headers)) {
            fclose($handle);
            throw new \Exception('CSV file appears to be empty or invalid');
        }

        // Normalize headers (trim whitespace)
        $headers = array_map('trim', $headers);

        $products = [];
        $currentProduct = null;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue; // Skip malformed rows
            }

            $data = array_combine($headers, $row);
            
            // Get product handle (unique identifier)
            $productHandle = $data['Handle'] ?? null;
            if (!$productHandle) {
                continue; // Skip rows without handle
            }

            // If this is a new product (has Title), start a new product
            if (!empty($data['Title'])) {
                $currentProduct = [
                    'handle' => $productHandle,
                    'title' => $data['Title'],
                    'body_html' => $data['Body (HTML)'] ?? '',
                    'vendor' => $data['Vendor'] ?? '',
                    'type' => $data['Type'] ?? '',
                    'tags' => $data['Tags'] ?? '',
                    'published' => ($data['Published'] ?? 'false') === 'true',
                    'images' => [],
                    'variants' => [],
                ];

                // Collect images
                if (!empty($data['Image Src'])) {
                    $currentProduct['images'][] = [
                        'src' => $data['Image Src'],
                        'position' => (int) ($data['Image Position'] ?? 1),
                        'alt' => $data['Image Alt Text'] ?? '',
                    ];
                }

                $products[$productHandle] = $currentProduct;
            }

            // Add variant if we have a current product
            if ($currentProduct && !empty($data['Variant Price'])) {
                $variant = [
                    'option1_name' => $data['Option1 Name'] ?? '',
                    'option1_value' => $data['Option1 Value'] ?? '',
                    'option2_name' => $data['Option2 Name'] ?? '',
                    'option2_value' => $data['Option2 Value'] ?? '',
                    'option3_name' => $data['Option3 Name'] ?? '',
                    'option3_value' => $data['Option3 Value'] ?? '',
                    'sku' => $data['Variant SKU'] ?? '',
                    'price' => $data['Variant Price'] ?? '',
                    'compare_at_price' => $data['Variant Compare At Price'] ?? '',
                    'barcode' => $data['Variant Barcode'] ?? '',
                    'grams' => (int) ($data['Variant Grams'] ?? 0),
                    'requires_shipping' => ($data['Variant Requires Shipping'] ?? 'true') === 'true',
                    'taxable' => ($data['Variant Taxable'] ?? 'true') === 'true',
                    'image' => $data['Variant Image'] ?? '',
                ];

                // Add variant image if different from main product images
                if (!empty($variant['image'])) {
                    $currentProduct['images'][] = [
                        'src' => $variant['image'],
                        'position' => count($currentProduct['images']) + 1,
                        'alt' => $data['Image Alt Text'] ?? '',
                    ];
                }

                $products[$productHandle]['variants'][] = $variant;
            }

            // Collect additional images from subsequent rows
            if ($currentProduct && !empty($data['Image Src']) && empty($data['Title'])) {
                $products[$productHandle]['images'][] = [
                    'src' => $data['Image Src'],
                    'position' => (int) ($data['Image Position'] ?? count($products[$handle]['images']) + 1),
                    'alt' => $data['Image Alt Text'] ?? '',
                ];
            }
        }

        fclose($handle);

        // Sort images by position
        foreach ($products as &$product) {
            usort($product['images'], function ($a, $b) {
                return $a['position'] <=> $b['position'];
            });
            // Remove duplicates
            $seen = [];
            $product['images'] = array_filter($product['images'], function ($img) use (&$seen) {
                if (in_array($img['src'], $seen)) {
                    return false;
                }
                $seen[] = $img['src'];
                return true;
            });
            $product['images'] = array_values($product['images']);
        }

        return [
            'products' => array_values($products),
            'total_products' => count($products),
            'total_variants' => array_sum(array_map(fn($p) => count($p['variants']), $products)),
        ];
    }

    /**
     * Import products from parsed data
     */
    public function import(string $filePath, string $stripeAccountId): array
    {
        $parsed = $this->parse($filePath);
        $products = $parsed['products'] ?? [];

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($products as $productData) {
            try {
                // Check if product already exists (by name)
                $existing = ConnectedProduct::where('name', $productData['title'])
                    ->where('stripe_account_id', $stripeAccountId)
                    ->first();

                if ($existing) {
                    Log::info('Product already exists, skipping', [
                        'name' => $productData['title'],
                        'stripe_account_id' => $stripeAccountId,
                    ]);
                    $skipped++;
                    continue;
                }

                // Create product
                $product = new ConnectedProduct([
                    'stripe_account_id' => $stripeAccountId,
                    'name' => $productData['title'],
                    'description' => $this->stripHtml($productData['body_html']),
                    'active' => $productData['published'],
                    'type' => 'good', // Physical product
                    'shippable' => true,
                    'product_meta' => [
                        'source' => 'shopify',
                        'handle' => $productData['handle'],
                        'vendor' => $productData['vendor'],
                        'type' => $productData['type'],
                        'tags' => $productData['tags'],
                    ],
                ]);

                // Create product in Stripe first
                $createAction = new CreateConnectedProductInStripe();
                $stripeProductId = $createAction($product);

                if (!$stripeProductId) {
                    Log::error('Failed to create product in Stripe', [
                        'name' => $productData['title'],
                    ]);
                    $errors++;
                    continue;
                }

                $product->stripe_product_id = $stripeProductId;
                $product->save();

                // Download and add images
                if (!empty($productData['images'])) {
                    $this->downloadAndAddImages($product, $productData['images']);
                }

                // Upload images to Stripe
                if ($product->hasMedia('images')) {
                    $uploadAction = new UploadProductImagesToStripe();
                    $imageUrls = $uploadAction($product);
                    if (!empty($imageUrls)) {
                        $product->images = $imageUrls;
                        $product->saveQuietly();
                    }
                }

                // Create prices and variants
                $variants = $productData['variants'] ?? [];
                if (empty($variants)) {
                    // If no variants, this is unusual for Shopify but handle gracefully
                    Log::info('Product has no variants', [
                        'product' => $productData['title'],
                    ]);
                } else {
                    // Create separate Stripe Product and Price for each variant
                    $firstPriceId = null;
                    foreach ($variants as $variantData) {
                        if (empty($variantData['price'])) {
                            continue;
                        }

                        $priceAmount = $this->parsePrice($variantData['price']);
                        if ($priceAmount <= 0) {
                            continue;
                        }

                        // Create variant record first (without Stripe IDs)
                        $variant = \App\Models\ProductVariant::updateOrCreate(
                            [
                                'connected_product_id' => $product->id,
                                'stripe_account_id' => $stripeAccountId,
                                'sku' => $variantData['sku'] ?? null,
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

                        // Create Stripe Product for this variant
                        $createVariantProductAction = new \App\Actions\ConnectedProducts\CreateVariantProductInStripe();
                        $variantStripeProductId = $createVariantProductAction($variant);

                        if (!$variantStripeProductId) {
                            Log::warning('Failed to create Stripe product for variant', [
                                'variant_id' => $variant->id,
                                'sku' => $variant->sku,
                            ]);
                            continue;
                        }

                        // Create Stripe Price for this variant product
                        $createPriceAction = new CreateConnectedPriceInStripe();
                        $priceId = $createPriceAction(
                            $variantStripeProductId, // Use variant's Stripe product ID
                            $stripeAccountId,
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

                            // Update variant with Stripe IDs
                            $variant->stripe_product_id = $variantStripeProductId;
                            $variant->stripe_price_id = $priceId;
                            $variant->saveQuietly();

                            // Also update the ConnectedPrice record to link it to the variant
                            \App\Models\ConnectedPrice::where('stripe_price_id', $priceId)
                                ->where('stripe_account_id', $stripeAccountId)
                                ->update(['product_variant_id' => $variant->id]);
                        }
                    }

                    // Set first price as default if we have one
                    if ($firstPriceId && empty($product->default_price)) {
                        $product->default_price = $firstPriceId;
                        $product->saveQuietly();
                    }
                }

                $imported++;
            } catch (\Exception $e) {
                Log::error('Error importing product', [
                    'product' => $productData['title'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
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
     * Build variant name from options
     */
    protected function buildVariantName(array $variant): string
    {
        $parts = [];
        
        if (!empty($variant['option1_value'])) {
            $parts[] = $variant['option1_value'];
        }
        if (!empty($variant['option2_value'])) {
            $parts[] = $variant['option2_value'];
        }
        if (!empty($variant['option3_value'])) {
            $parts[] = $variant['option3_value'];
        }
        
        return implode(' / ', $parts) ?: 'Default';
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
}

