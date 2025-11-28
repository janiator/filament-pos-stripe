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

        // Store all rows for two-pass processing
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue; // Skip malformed rows
            }
            $rows[] = array_combine($headers, $row);
        }
        fclose($handle);

        $products = [];

        // PASS 1: Create all main products (rows with Title)
        foreach ($rows as $data) {
            $productHandle = trim($data['Handle'] ?? '');
            if (empty($productHandle)) {
                continue; // Skip rows without handle
            }

            // Only process rows with Title (main products)
            if (!empty($data['Title'])) {
                $products[$productHandle] = [
                    'handle' => $productHandle,
                    'title' => $data['Title'],
                    'body_html' => $data['Body (HTML)'] ?? '',
                    'vendor' => $data['Vendor'] ?? '',
                    'type' => $data['Type'] ?? '',
                    'tags' => $data['Tags'] ?? '',
                    'published' => ($data['Published'] ?? 'false') === 'true',
                    'images' => [],
                    'variants' => [],
                    'variant_count' => 0,
                    'variant_min_price' => null,
                    'variant_max_price' => null,
                ];

                // Collect main product image
                $mainImageSrc = trim($data['Image Src'] ?? '');
                if (!empty($mainImageSrc)) {
                    $products[$productHandle]['images'][] = [
                        'src' => $mainImageSrc,
                        'position' => (int) ($data['Image Position'] ?? 1),
                        'alt' => trim($data['Image Alt Text'] ?? ''),
                    ];
                }
            }
        }

        // PASS 2: Add all variants to existing products (rows with Variant Price)
        foreach ($rows as $data) {
            $productHandle = trim($data['Handle'] ?? '');
            if (empty($productHandle)) {
                continue; // Skip rows without handle
            }

            // Skip if product doesn't exist (shouldn't happen in well-formed CSV)
            if (!isset($products[$productHandle])) {
                continue;
            }

            // Check if this row has variant data
            $variantPrice = trim($data['Variant Price'] ?? '');
            $title = trim($data['Title'] ?? '');
            $imageSrc = trim($data['Image Src'] ?? '');
            
            if (empty($variantPrice)) {
                // No variant price - this is either an image-only row or a row with no data
                // Image-only rows: Have Image Src but no Title and no Variant Price
                if (!empty($imageSrc) && empty($title)) {
                    $products[$productHandle]['images'][] = [
                        'src' => $imageSrc,
                        'position' => (int) ($data['Image Position'] ?? count($products[$productHandle]['images']) + 1),
                        'alt' => trim($data['Image Alt Text'] ?? ''),
                    ];
                    
                    Log::debug('Added additional image to product', [
                        'handle' => $productHandle,
                        'product_title' => $products[$productHandle]['title'] ?? 'N/A',
                        'image_src' => $imageSrc,
                        'image_position' => $data['Image Position'] ?? null,
                    ]);
                }
                continue; // Skip rows without variant data
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
            
            $priceValue = $variantPrice;
            // Normalize price for summary (float)
            $priceNumeric = is_numeric($priceValue) ? (float) $priceValue : null;

            $variant = [
                'option1_name' => $data['Option1 Name'] ?? '',
                'option1_value' => $data['Option1 Value'] ?? '',
                'option2_name' => $data['Option2 Name'] ?? '',
                'option2_value' => $data['Option2 Value'] ?? '',
                'option3_name' => $data['Option3 Name'] ?? '',
                'option3_value' => $data['Option3 Value'] ?? '',
                'sku' => $data['Variant SKU'] ?? '',
                'price' => $variantPrice,
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
            $variantImage = trim($variant['image'] ?? '');
            if (!empty($variantImage)) {
                $products[$productHandle]['images'][] = [
                    'src' => $variantImage,
                    'position' => count($products[$productHandle]['images']) + 1,
                    'alt' => trim($data['Image Alt Text'] ?? ''),
                ];
                
                Log::debug('Added variant image to product', [
                    'handle' => $productHandle,
                    'product_title' => $products[$productHandle]['title'] ?? 'N/A',
                    'variant_image' => $variantImage,
                ]);
            }

            // Add variant to the product's variants array
            $products[$productHandle]['variants'][] = $variant;

            // Track summary data
            $products[$productHandle]['variant_count']++;
            if ($priceNumeric !== null) {
                if ($products[$productHandle]['variant_min_price'] === null || $priceNumeric < $products[$productHandle]['variant_min_price']) {
                    $products[$productHandle]['variant_min_price'] = $priceNumeric;
                }
                if ($products[$productHandle]['variant_max_price'] === null || $priceNumeric > $products[$productHandle]['variant_max_price']) {
                    $products[$productHandle]['variant_max_price'] = $priceNumeric;
                }
            }
            
            // Debug logging
            Log::info('Added variant to product', [
                'handle' => $productHandle,
                'product_title' => $products[$productHandle]['title'] ?? 'N/A',
                'variant_price' => $variant['price'],
                'variant_options' => [
                    'option1' => $variant['option1_value'] ?? null,
                    'option2' => $variant['option2_value'] ?? null,
                    'option3' => $variant['option3_value'] ?? null,
                ],
                'total_variants_for_product' => $products[$productHandle]['variant_count'],
            ]);
        }

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
        unset($product);

        // Debug: Log variant counts and image counts per product
        foreach ($products as $handle => $product) {
            $variantCount = $product['variant_count'] ?? count($product['variants'] ?? []);
            $imageCount = count($product['images'] ?? []);
            Log::info('Product parsed from CSV', [
                'handle' => $handle,
                'title' => $product['title'] ?? 'N/A',
                'variant_count' => $variantCount,
                'image_count' => $imageCount,
                'images' => array_map(function($img) {
                    return [
                        'src' => $img['src'] ?? null,
                        'position' => $img['position'] ?? null,
                    ];
                }, $product['images'] ?? []),
                'variants' => array_map(function($v) {
                    return [
                        'price' => $v['price'] ?? null,
                        'option1' => $v['option1_value'] ?? null,
                        'option2' => $v['option2_value'] ?? null,
                        'option3' => $v['option3_value'] ?? null,
                    ];
                }, $product['variants'] ?? []),
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
        $errorDetails = [];
        $totalProducts = count($products);

        Log::info('Starting product import', [
            'total_products' => $totalProducts,
            'stripe_account_id' => $stripeAccountId,
        ]);

        foreach ($products as $index => $productData) {
            $productIndex = $index + 1;
            Log::info("Processing product {$productIndex}/{$totalProducts}", [
                'product_title' => $productData['title'] ?? 'Unknown',
                'product_handle' => $productData['handle'] ?? 'Unknown',
            ]);
            
            try {
                // Skip products without title or handle
                if (empty($productData['title']) || empty($productData['handle'])) {
                    Log::warning('Skipping product - missing title or handle', [
                        'title' => $productData['title'] ?? null,
                        'handle' => $productData['handle'] ?? null,
                        'product_index' => $productIndex,
                    ]);
                    $errors++;
                    continue;
                }
                
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

                // Determine if this is a single or variable product
                $variants = $productData['variants'] ?? [];
                $isVariable = count($variants) > 1;

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

                // Create product in Stripe for both single and variable products
                // Variable products need a main product ID too (even though only variants are used for pricing)
                $createAction = app(CreateConnectedProductInStripe::class);
                $stripeProductId = $createAction($product);

                if (!$stripeProductId) {
                    Log::error('Failed to create product in Stripe', [
                        'name' => $productData['title'],
                        'is_variable' => $isVariable,
                    ]);
                    $errors++;
                    continue;
                }

                $product->stripe_product_id = $stripeProductId;

                $product->save();

                // Download and add images
                if (!empty($productData['images'])) {
                    try {
                        $this->downloadAndAddImages($product, $productData['images']);
                    } catch (\Exception $e) {
                        // Log but don't fail the import if image download fails
                        Log::warning('Failed to download some images for product', [
                            'product' => $productData['title'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Upload images to Stripe
                if ($product->hasMedia('images')) {
                    $uploadAction = app(UploadProductImagesToStripe::class);
                    $imageUrls = $uploadAction($product);
                    if (!empty($imageUrls)) {
                        $product->images = $imageUrls;
                        $product->saveQuietly();
                    }
                }

                // Create prices and variants
                Log::info('Processing product variants', [
                    'product' => $productData['title'],
                    'variant_count' => count($variants),
                    'is_variable' => $isVariable,
                    'variants' => $variants,
                ]);
                
                if (empty($variants)) {
                    // If no variants, this is unusual for Shopify but handle gracefully
                    Log::info('Product has no variants', [
                        'product' => $productData['title'],
                        'product_data' => $productData,
                    ]);
                } else {
                    // For variable products: Create separate Stripe Product and Price for each variant
                    // For single products: Don't create variants in Stripe (main product already created)
                    $firstPriceId = null;
                    $firstPriceAmount = null; // Track first variant's price in cents
                    $firstCurrency = 'nok'; // Track first variant's currency
                    
                    Log::info('Starting variant import loop', [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'total_variants_to_process' => count($variants),
                        'is_variable' => $isVariable,
                    ]);
                    
                    $variantsProcessed = 0;
                    $variantsCreated = 0;
                    $variantsUpdated = 0;
                    
                    foreach ($variants as $index => $variantData) {
                        $variantsProcessed++;
                        
                        Log::debug('Processing variant', [
                            'product_id' => $product->id,
                            'variant_index' => $index,
                            'variant_price' => $variantData['price'] ?? null,
                            'variant_sku' => $variantData['sku'] ?? null,
                            'option1' => $variantData['option1_value'] ?? null,
                            'option2' => $variantData['option2_value'] ?? null,
                            'option3' => $variantData['option3_value'] ?? null,
                        ]);
                        
                        if (empty($variantData['price'])) {
                            Log::debug('Skipping variant - no price', [
                                'variant_index' => $index,
                            ]);
                            continue;
                        }

                        $priceAmount = $this->parsePrice($variantData['price']);
                        if ($priceAmount <= 0) {
                            continue;
                        }

                        // Normalize SKU - convert empty string to null (not empty string)
                        $sku = !empty(trim($variantData['sku'] ?? '')) ? trim($variantData['sku']) : null;
                        
                        // Check if variant with same product, account, and options already exists
                        // Use SKU if available, otherwise use option values
                        $existingVariant = null;
                        if ($sku) {
                            // If SKU is provided, use it for lookup (more reliable)
                            $existingVariant = \App\Models\ProductVariant::where('connected_product_id', $product->id)
                                ->where('stripe_account_id', $stripeAccountId)
                                ->where('sku', $sku)
                                ->first();
                        }
                        
                        // If not found by SKU, try option values
                        if (!$existingVariant) {
                            $query = \App\Models\ProductVariant::where('connected_product_id', $product->id)
                                ->where('stripe_account_id', $stripeAccountId);
                            
                            // Build query with proper null handling
                            $option1 = $variantData['option1_value'] ?? null;
                            $option2 = $variantData['option2_value'] ?? null;
                            $option3 = $variantData['option3_value'] ?? null;
                            
                            if ($option1 !== null && $option1 !== '') {
                                $query->where('option1_value', $option1);
                            } else {
                                $query->whereNull('option1_value');
                            }
                            
                            if ($option2 !== null && $option2 !== '') {
                                $query->where('option2_value', $option2);
                            } else {
                                $query->whereNull('option2_value');
                            }
                            
                            if ($option3 !== null && $option3 !== '') {
                                $query->where('option3_value', $option3);
                            } else {
                                $query->whereNull('option3_value');
                            }
                            
                            $existingVariant = $query->first();
                        }
                        
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
                            
                            $variantsUpdated++;
                            Log::info('Updated existing variant', [
                                'variant_id' => $variant->id,
                                'product_id' => $product->id,
                                'option1' => $variantData['option1_value'] ?? null,
                                'option2' => $variantData['option2_value'] ?? null,
                                'option3' => $variantData['option3_value'] ?? null,
                                'variants_processed' => $variantsProcessed,
                            ]);
                        } else {
                            // Create new variant - use withoutEvents to prevent automatic Stripe creation
                            // We'll handle Stripe creation manually based on product type
                            $variant = \App\Models\ProductVariant::withoutEvents(function () use (
                                $product, $stripeAccountId, $sku, $variantData, $priceAmount
                            ) {
                                return \App\Models\ProductVariant::create([
                                    'connected_product_id' => $product->id,
                                    'stripe_account_id' => $stripeAccountId,
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
                            });
                            
                            $variantsCreated++;
                            Log::info('Created new variant', [
                                'variant_id' => $variant->id,
                                'product_id' => $product->id,
                                'option1' => $variantData['option1_value'] ?? null,
                                'option2' => $variantData['option2_value'] ?? null,
                                'option3' => $variantData['option3_value'] ?? null,
                                'sku' => $sku,
                                'is_variable' => $isVariable,
                                'variants_processed' => $variantsProcessed,
                                'variants_created' => $variantsCreated,
                            ]);
                        }

                        // For variable products: Create Stripe Product and Price for each variant
                        // For single products: Don't create variant in Stripe, just set main product price
                        if ($isVariable) {
                            // Create Stripe Product for this variant
                            $createVariantProductAction = app(\App\Actions\ConnectedProducts\CreateVariantProductInStripe::class);
                            $variantStripeProductId = $createVariantProductAction($variant);

                            if (!$variantStripeProductId) {
                                Log::warning('Failed to create Stripe product for variant', [
                                    'variant_id' => $variant->id,
                                    'sku' => $variant->sku,
                                ]);
                                continue;
                            }

                            // Create Stripe Price for this variant product
                            $createPriceAction = app(CreateConnectedPriceInStripe::class);
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
                                // Update variant with Stripe IDs
                                $variant->stripe_product_id = $variantStripeProductId;
                                $variant->stripe_price_id = $priceId;
                                $variant->saveQuietly();
                            }
                        } else {
                            // Single product: Create price for main product (not variant)
                            if (!$firstPriceId && $product->stripe_product_id) {
                                $createPriceAction = app(CreateConnectedPriceInStripe::class);
                                $priceId = $createPriceAction(
                                    $product->stripe_product_id,
                                    $stripeAccountId,
                                    $priceAmount,
                                    'nok',
                                    [
                                        'nickname' => $product->name,
                                        'metadata' => [
                                            'source' => 'shopify',
                                        ],
                                    ]
                                );

                                if ($priceId) {
                                    $firstPriceId = $priceId;
                                    $firstPriceAmount = $priceAmount;
                                    $firstCurrency = 'nok';
                                }
                            }
                        }
                    }

                    Log::info('Finished variant import loop', [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'variants_processed' => $variantsProcessed,
                        'variants_created' => $variantsCreated,
                        'variants_updated' => $variantsUpdated,
                        'total_variants_in_array' => count($variants),
                    ]);
                    
                    // Set main product price from first variant (only for single products)
                    if (!$isVariable && $firstPriceId && $firstPriceAmount) {
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
                Log::info("Successfully imported product {$productIndex}/{$totalProducts}", [
                    'product_title' => $productData['title'] ?? 'Unknown',
                    'imported_count' => $imported,
                ]);
            } catch (\Exception $e) {
                $productTitle = $productData['title'] ?? 'Unknown';
                $errorMessage = "Product '{$productTitle}': {$e->getMessage()}";
                $errorDetails[] = $errorMessage;
                Log::error('Error importing product', [
                    'product' => $productData['title'] ?? 'Unknown',
                    'product_index' => $productIndex,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $errors++;
                // Continue to next product instead of stopping
            } catch (\Throwable $e) {
                $productTitle = $productData['title'] ?? 'Unknown';
                $errorMessage = "Product '{$productTitle}': {$e->getMessage()}";
                $errorDetails[] = $errorMessage;
                Log::error('Fatal error importing product', [
                    'product' => $productData['title'] ?? 'Unknown',
                    'product_index' => $productIndex,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $errors++;
                // Continue to next product instead of stopping
            }
        }

        Log::info('Finished product import', [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_products' => $totalProducts,
            'error_details' => $errorDetails,
        ]);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errorDetails,
        ];
    }

    /**
     * Download and add images to product
     */
    protected function downloadAndAddImages(ConnectedProduct $product, array $images): void
    {
        // Skip image downloads in test environment to avoid timeouts
        if (app()->environment('testing')) {
            Log::info('Skipping image downloads in test environment', [
                'product_id' => $product->id,
                'image_count' => count($images),
            ]);
            return;
        }
        
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

