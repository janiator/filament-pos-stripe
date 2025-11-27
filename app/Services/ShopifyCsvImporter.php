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
                // Make sure currentProduct is a reference to the array element
                $currentProduct = &$products[$productHandle];
            } else {
                // If no Title but we have a handle, this might be a variant row for an existing product
                // Update currentProduct to point to the existing product in the array
                if (isset($products[$productHandle])) {
                    $currentProduct = &$products[$productHandle];
                }
            }

            // Add variant if we have a product (either current or from array) and variant data
            // Check if Variant Price exists and is not empty (trim whitespace)
            $variantPrice = trim($data['Variant Price'] ?? '');
            if (!empty($variantPrice)) {
                // Make sure we have the product - check array if currentProduct is null
                if (!$currentProduct && isset($products[$productHandle])) {
                    $currentProduct = &$products[$productHandle];
                }
                
                // Ensure we're working with the array directly - product must exist
                if (isset($products[$productHandle])) {
                    // Ensure currentProduct is a reference to the array element
                    if (!$currentProduct || $currentProduct !== $products[$productHandle]) {
                        $currentProduct = &$products[$productHandle];
                    }
                    // Parse inventory fields
                    $inventoryQuantity = null;
                    $inventoryPolicy = 'deny';
                    if (!empty($data['Variant Inventory Tracker']) && $data['Variant Inventory Tracker'] !== 'shopify') {
                        // If inventory tracker is not shopify, we might not track inventory
                        $inventoryQuantity = null;
                    } elseif (isset($data['Variant Inventory Qty'])) {
                        // Some CSV exports use "Variant Inventory Qty" instead
                        $inventoryQuantity = !empty($data['Variant Inventory Qty']) ? (int) $data['Variant Inventory Qty'] : null;
                    }
                    
                    if (!empty($data['Variant Inventory Policy'])) {
                        $inventoryPolicy = strtolower(trim($data['Variant Inventory Policy']));
                        if (!in_array($inventoryPolicy, ['deny', 'continue'])) {
                            $inventoryPolicy = 'deny';
                        }
                    }
                    
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
                        'inventory_quantity' => $inventoryQuantity,
                        'inventory_policy' => $inventoryPolicy,
                        'inventory_management' => !empty($data['Variant Inventory Tracker']) ? $data['Variant Inventory Tracker'] : null,
                    ];

                    // Add variant image if different from main product images
                    if (!empty($variant['image'])) {
                        $currentProduct['images'][] = [
                            'src' => $variant['image'],
                            'position' => count($currentProduct['images']) + 1,
                            'alt' => $data['Image Alt Text'] ?? '',
                        ];
                    }

                    // Add variant to the product's variants array
                    // Since currentProduct is a reference, we can use either
                    $products[$productHandle]['variants'][] = $variant;
                    
                    // Debug logging
                    Log::debug('Added variant to product', [
                        'handle' => $productHandle,
                        'variant_price' => $variant['price'],
                        'variant_options' => [
                            'option1' => $variant['option1_value'] ?? null,
                            'option2' => $variant['option2_value'] ?? null,
                            'option3' => $variant['option3_value'] ?? null,
                        ],
                        'total_variants_for_product' => count($products[$productHandle]['variants']),
                    ]);
                } else {
                    Log::warning('Could not add variant - product not found', [
                        'handle' => $productHandle,
                        'variant_price' => $variantPrice,
                        'products_keys' => array_keys($products),
                    ]);
                }
            }

            // Collect additional images from subsequent rows
            if (!empty($data['Image Src']) && empty($data['Title'])) {
                // Make sure we have the product - check array if currentProduct is null
                if (!$currentProduct && isset($products[$productHandle])) {
                    $currentProduct = &$products[$productHandle];
                }
                
                if ($currentProduct) {
                    $products[$productHandle]['images'][] = [
                        'src' => $data['Image Src'],
                        'position' => (int) ($data['Image Position'] ?? count($products[$productHandle]['images']) + 1),
                        'alt' => $data['Image Alt Text'] ?? '',
                    ];
                }
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

        // Debug: Log variant counts per product
        foreach ($products as $handle => $product) {
            Log::debug('Product variant count', [
                'handle' => $handle,
                'title' => $product['title'] ?? 'N/A',
                'variant_count' => count($product['variants'] ?? []),
            ]);
        }
        
        return [
            'products' => array_values($products),
            'total_products' => count($products),
            'total_variants' => array_sum(array_map(fn($p) => count($p['variants'] ?? []), $products)),
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
                Log::info('Processing product variants', [
                    'product' => $productData['title'],
                    'variant_count' => count($variants),
                    'variants' => $variants,
                ]);
                
                if (empty($variants)) {
                    // If no variants, this is unusual for Shopify but handle gracefully
                    Log::info('Product has no variants', [
                        'product' => $productData['title'],
                        'product_data' => $productData,
                    ]);
                } else {
                    // Create separate Stripe Product and Price for each variant
                    $firstPriceId = null;
                    $firstPriceAmount = null; // Track first variant's price in cents
                    $firstCurrency = 'nok'; // Track first variant's currency
                    foreach ($variants as $variantData) {
                        if (empty($variantData['price'])) {
                            continue;
                        }

                        $priceAmount = $this->parsePrice($variantData['price']);
                        if ($priceAmount <= 0) {
                            continue;
                        }

                        // Normalize SKU - convert empty string to null (not empty string)
                        $sku = !empty(trim($variantData['sku'] ?? '')) ? trim($variantData['sku']) : null;
                        
                        // Check if variant with same product, account, and options already exists
                        // This prevents duplicates when SKU is null
                        $existingVariant = \App\Models\ProductVariant::where('connected_product_id', $product->id)
                            ->where('stripe_account_id', $stripeAccountId)
                            ->where('option1_value', $variantData['option1_value'] ?? null)
                            ->where('option2_value', $variantData['option2_value'] ?? null)
                            ->where('option3_value', $variantData['option3_value'] ?? null)
                            ->first();
                        
                        // Create or update variant record
                        if ($existingVariant) {
                            $variant = $existingVariant;
                            // Update existing variant
                            $variant->fill([
                                'sku' => $sku, // Update SKU if provided
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
                        } else {
                            // Create new variant - use updateOrCreate with SKU to handle unique constraint
                            // If SKU is null, PostgreSQL allows multiple nulls in unique constraint
                            $variant = \App\Models\ProductVariant::updateOrCreate(
                                [
                                    'connected_product_id' => $product->id,
                                    'stripe_account_id' => $stripeAccountId,
                                    'sku' => $sku, // Can be null
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
                                $firstPriceAmount = $priceAmount; // Store first variant's price in cents
                                $firstCurrency = 'nok'; // Store first variant's currency
                            }

                            // Update variant with Stripe IDs
                            $variant->stripe_product_id = $variantStripeProductId;
                            $variant->stripe_price_id = $priceId;
                            $variant->saveQuietly();
                            
                            // Note: ConnectedPrice doesn't have product_variant_id column
                            // The relationship is: ProductVariant -> stripe_price_id -> ConnectedPrice
                        }
                    }

                    // Set main product price from first variant
                    if ($firstPriceId && $firstPriceAmount) {
                        // Set default_price (Stripe price ID)
                        if (empty($product->default_price)) {
                            $product->default_price = $firstPriceId;
                        }
                        
                        // Set price field (decimal format, e.g., 299.00)
                        // Convert from cents to decimal
                        $product->price = number_format($firstPriceAmount / 100, 2, '.', '');
                        $product->currency = $firstCurrency;
                        $product->saveQuietly();
                        
                        Log::info('Set main product price from first variant', [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'price' => $product->price,
                            'currency' => $product->currency,
                            'default_price' => $product->default_price,
                            'first_price_amount' => $firstPriceAmount,
                        ]);
                    } else {
                        Log::warning('Could not set main product price - no first variant price', [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'firstPriceId' => $firstPriceId,
                            'firstPriceAmount' => $firstPriceAmount,
                            'variants_count' => count($variants),
                        ]);
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

