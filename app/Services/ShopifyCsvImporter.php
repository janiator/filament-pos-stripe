<?php

namespace App\Services;

use App\Actions\ConnectedProducts\CreateConnectedProductInStripe;
use App\Actions\ConnectedPrices\CreateConnectedPriceInStripe;
use App\Actions\ConnectedProducts\UploadProductImagesToStripe;
use App\Models\ConnectedProduct;
use Illuminate\Support\Facades\Log;

class ShopifyCsvImporter
{
    /**
     * Parse CSV file and return structured data
     */
    public function parse(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new \Exception("CSV file not found: {$filePath}");
        }

        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            throw new \Exception("Could not open CSV file: {$filePath}");
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (! $headers || empty($headers)) {
            fclose($handle);
            throw new \Exception('CSV file appears to be empty or invalid');
        }

        // Normalize headers (trim whitespace + BOM safety)
        $headers = array_map(function ($h) {
            $h = (string) $h;
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
            return trim($h);
        }, $headers);

        // Store all rows for two-pass processing
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                // Keep old behaviour: skip malformed rows
                continue;
            }
            $rows[] = array_combine($headers, $row);
        }
        fclose($handle);

        $products = [];

        // PASS 1: Ensure product shells exist for every handle, enrich when Title is present
        foreach ($rows as $data) {
            $productHandle = trim((string) ($data['Handle'] ?? ''));
            if ($productHandle === '') {
                continue;
            }

            if (! isset($products[$productHandle])) {
                $products[$productHandle] = [
                    'handle' => $productHandle,
                    'title' => null,
                    'body_html' => '',
                    'vendor' => '',
                    'type' => '',
                    'tags' => '',
                    'category' => $data['Product Category'] ?? ($data['Category'] ?? ''),
                    'published' => false,
                    'images' => [],
                    'variants' => [],
                    'variant_count' => 0,
                    'variant_min_price' => null,
                    'variant_max_price' => null,
                ];
            }

            if (! empty($data['Title'])) {
                $products[$productHandle]['title'] = $data['Title'];
                $products[$productHandle]['body_html'] = $data['Body (HTML)'] ?? $products[$productHandle]['body_html'];
                $products[$productHandle]['vendor'] = $data['Vendor'] ?? $products[$productHandle]['vendor'];
                $products[$productHandle]['type'] = $data['Type'] ?? $products[$productHandle]['type'];
                $products[$productHandle]['tags'] = $data['Tags'] ?? $products[$productHandle]['tags'];
                $products[$productHandle]['category'] = $data['Product Category'] ?? ($data['Category'] ?? $products[$productHandle]['category']);
                $products[$productHandle]['published'] = (($data['Published'] ?? 'false') === 'true');

                // Collect main product image
                $mainImageSrc = trim((string) ($data['Image Src'] ?? ''));
                if ($mainImageSrc !== '') {
                    $products[$productHandle]['images'][] = [
                        'src' => $mainImageSrc,
                        'position' => (int) ($data['Image Position'] ?? 1),
                        'alt' => trim((string) ($data['Image Alt Text'] ?? '')),
                    ];
                }
            }
        }

        // PASS 2: Add all variants + image-only rows
        foreach ($rows as $data) {
            $productHandle = trim((string) ($data['Handle'] ?? ''));
            if ($productHandle === '' || ! isset($products[$productHandle])) {
                continue;
            }

            $variantPrice = trim((string) ($data['Variant Price'] ?? ''));
            $title = trim((string) ($data['Title'] ?? ''));
            $imageSrc = trim((string) ($data['Image Src'] ?? ''));

            if ($variantPrice === '') {
                // Image-only rows: have Image Src but no Title and no Variant Price
                if ($imageSrc !== '' && $title === '') {
                    $products[$productHandle]['images'][] = [
                        'src' => $imageSrc,
                        'position' => (int) ($data['Image Position'] ?? (count($products[$productHandle]['images']) + 1)),
                        'alt' => trim((string) ($data['Image Alt Text'] ?? '')),
                    ];

                    Log::debug('Added additional image to product', [
                        'handle' => $productHandle,
                        'product_title' => $products[$productHandle]['title'] ?? 'N/A',
                        'image_src' => $imageSrc,
                        'image_position' => $data['Image Position'] ?? null,
                    ]);
                }
                continue;
            }

            // Parse inventory fields
            $inventoryQuantity = null;
            $inventoryPolicy = 'deny';

            if (! empty($data['Variant Inventory Tracker']) && $data['Variant Inventory Tracker'] !== 'shopify') {
                $inventoryQuantity = null;
            } elseif (isset($data['Variant Inventory Qty'])) {
                $inventoryQuantity = ! empty($data['Variant Inventory Qty']) ? (int) $data['Variant Inventory Qty'] : null;
            }

            if (! empty($data['Variant Inventory Policy'])) {
                $inventoryPolicy = strtolower(trim((string) $data['Variant Inventory Policy']));
                if (! in_array($inventoryPolicy, ['deny', 'continue'])) {
                    $inventoryPolicy = 'deny';
                }
            }

            $priceNumeric = is_numeric($variantPrice) ? (float) $variantPrice : null;

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
                'inventory_management' => ! empty($data['Variant Inventory Tracker']) ? $data['Variant Inventory Tracker'] : null,
            ];

            // Add variant image if present
            $variantImage = trim((string) ($variant['image'] ?? ''));
            if ($variantImage !== '') {
                $products[$productHandle]['images'][] = [
                    'src' => $variantImage,
                    'position' => count($products[$productHandle]['images']) + 1,
                    'alt' => trim((string) ($data['Image Alt Text'] ?? '')),
                ];

                Log::debug('Added variant image to product', [
                    'handle' => $productHandle,
                    'product_title' => $products[$productHandle]['title'] ?? 'N/A',
                    'variant_image' => $variantImage,
                ]);
            }

            $products[$productHandle]['variants'][] = $variant;

            $products[$productHandle]['variant_count']++;

            if ($priceNumeric !== null) {
                if ($products[$productHandle]['variant_min_price'] === null || $priceNumeric < $products[$productHandle]['variant_min_price']) {
                    $products[$productHandle]['variant_min_price'] = $priceNumeric;
                }
                if ($products[$productHandle]['variant_max_price'] === null || $priceNumeric > $products[$productHandle]['variant_max_price']) {
                    $products[$productHandle]['variant_max_price'] = $priceNumeric;
                }
            }

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

        // Sort images by position + dedupe by src
        foreach ($products as &$product) {
            usort($product['images'], function ($a, $b) {
                return ($a['position'] ?? 0) <=> ($b['position'] ?? 0);
            });

            $seen = [];
            $product['images'] = array_filter($product['images'], function ($img) use (&$seen) {
                $src = (string) ($img['src'] ?? '');
                if ($src === '' || in_array($src, $seen, true)) {
                    return false;
                }
                $seen[] = $src;
                return true;
            });

            $product['images'] = array_values($product['images']);

            // If a title never showed up, keep safe string to avoid later null issues
            $product['title'] = $product['title'] ?? '';
        }
        unset($product);

        $result = [
            'products' => array_values($products),
            'total_products' => count($products),
            'total_variants' => array_sum(array_map(fn ($p) => count($p['variants'] ?? []), $products)),
        ];

        $result['stats'] = $this->buildParseStats($result);

        // Debug: Log variant counts and image counts per product (keep your style)
        foreach ($products as $handle => $product) {
            $variantCount = $product['variant_count'] ?? count($product['variants'] ?? []);
            $imageCount = count($product['images'] ?? []);
            Log::info('Product parsed from CSV', [
                'handle' => $handle,
                'title' => $product['title'] ?? 'N/A',
                'variant_count' => $variantCount,
                'image_count' => $imageCount,
                'images' => array_map(function ($img) {
                    return [
                        'src' => $img['src'] ?? null,
                        'position' => $img['position'] ?? null,
                    ];
                }, $product['images'] ?? []),
                'variants' => array_map(function ($v) {
                    return [
                        'price' => $v['price'] ?? null,
                        'option1' => $v['option1_value'] ?? null,
                        'option2' => $v['option2_value'] ?? null,
                        'option3' => $v['option3_value'] ?? null,
                    ];
                }, $product['variants'] ?? []),
            ]);
        }

        Log::info('Shopify CSV parse summary', [
            'stats' => $result['stats'],
        ]);

        return $result;
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
        $errorCount = 0;
        $errorDetails = [];
        $totalProducts = count($products);

        Log::info('Starting product import', [
            'total_products' => $totalProducts,
            'stripe_account_id' => $stripeAccountId,
        ]);

        foreach ($products as $index => $productData) {
            $productIndex = $index + 1;

            $this->logProgress('shopify.import.products', $productIndex, $totalProducts, [
                'title' => $productData['title'] ?? null,
                'handle' => $productData['handle'] ?? null,
            ]);

            try {
                if (empty($productData['title']) || empty($productData['handle'])) {
                    $errorCount++;
                    $errorDetails[] = "Missing title/handle at row group index {$productIndex}";
                    Log::warning('Skipping product - missing title or handle', [
                        'title' => $productData['title'] ?? null,
                        'handle' => $productData['handle'] ?? null,
                        'product_index' => $productIndex,
                    ]);
                    continue;
                }

                $handle = (string) $productData['handle'];

                // Prefer handle-based dedupe (Shopify truth), fallback to name
                $existing = ConnectedProduct::where('stripe_account_id', $stripeAccountId)
                    ->where('product_meta->handle', $handle)
                    ->first();

                if (! $existing) {
                    $existing = ConnectedProduct::where('name', $productData['title'])
                        ->where('stripe_account_id', $stripeAccountId)
                        ->first();
                }

                if ($existing) {
                    Log::info('Product already exists, skipping', [
                        'name' => $productData['title'],
                        'handle' => $handle,
                        'stripe_account_id' => $stripeAccountId,
                    ]);
                    $skipped++;
                    continue;
                }

                $variants = $productData['variants'] ?? [];
                $variantCount = is_array($variants) ? count($variants) : 0;

                // One-line: Shopify single products still have 1 CSV variant row; treat <=1 as single in our domain.
                $isVariable = $variantCount > 1;

                $product = new ConnectedProduct([
                    'stripe_account_id' => $stripeAccountId,
                    'name' => $productData['title'],
                    'description' => $this->stripHtml($productData['body_html']),
                    'active' => (bool) ($productData['published'] ?? false),
                    'type' => 'good',
                    'shippable' => true,
                    'product_meta' => [
                        'source' => 'shopify',
                        'handle' => $handle,
                        'vendor' => $productData['vendor'] ?? '',
                        'type' => $productData['type'] ?? '',
                        'tags' => $productData['tags'] ?? '',
                        'category' => $productData['category'] ?? '',
                    ],
                ]);

                $createAction = app(CreateConnectedProductInStripe::class);
                $stripeProductId = $createAction($product);

                if (! $stripeProductId) {
                    $errorCount++;
                    $errorDetails[] = "Stripe product create failed for '{$productData['title']}' ({$handle})";
                    Log::error('Failed to create product in Stripe', [
                        'name' => $productData['title'],
                        'handle' => $handle,
                        'is_variable' => $isVariable,
                    ]);
                    continue;
                }

                $product->stripe_product_id = $stripeProductId;
                $product->save();

                if (! empty($productData['images'])) {
                    try {
                        $this->downloadAndAddImages($product, $productData['images']);
                    } catch (\Exception $e) {
                        Log::warning('Failed to download some images for product', [
                            'product' => $productData['title'],
                            'handle' => $handle,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($product->hasMedia('images')) {
                    $uploadAction = app(UploadProductImagesToStripe::class);
                    $imageUrls = $uploadAction($product);
                    if (! empty($imageUrls)) {
                        $product->images = $imageUrls;
                        $product->saveQuietly();
                    }
                }

                Log::info('Processing product variants', [
                    'product' => $productData['title'],
                    'handle' => $handle,
                    'variant_count' => $variantCount,
                    'is_variable' => $isVariable,
                ]);

                if ($variantCount === 0) {
                    // Keep your defensive log style
                    Log::info('Product has no variants', [
                        'product' => $productData['title'],
                        'handle' => $handle,
                    ]);
                }

                if ($isVariable) {
                    $this->importVariableVariants($product, $variants, $stripeAccountId, $errorDetails, $errorCount);
                } else {
                    $this->importSinglePriceOnly($product, $variants, $stripeAccountId, $errorDetails, $errorCount);
                }

                $imported++;

                Log::info("Successfully imported product {$productIndex}/{$totalProducts}", [
                    'product_title' => $productData['title'] ?? 'Unknown',
                    'handle' => $handle,
                    'imported_count' => $imported,
                ]);
            } catch (\Throwable $e) {
                $productTitle = $productData['title'] ?? 'Unknown';
                $handle = $productData['handle'] ?? 'Unknown';
                $errorCount++;
                $errorMessage = "Product '{$productTitle}' ({$handle}): {$e->getMessage()}";
                $errorDetails[] = $errorMessage;

                Log::error('Error importing product', [
                    'product' => $productTitle,
                    'handle' => $handle,
                    'product_index' => $productIndex,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        $result = [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errorDetails,
            'error_count' => $errorCount,
            'stats' => [
                'parse' => $parsed['stats'] ?? $this->buildParseStats($parsed),
                'import' => [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'error_count' => $errorCount,
                    'total_products' => $totalProducts,
                ],
            ],
        ];

        Log::info('Finished product import', [
            'stats' => $result['stats'],
            'first_errors' => array_slice($errorDetails, 0, 15),
        ]);

        return $result;
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
                if (! $imageUrl || ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
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

        if (! empty($variant['option1_value'])) {
            $parts[] = $variant['option1_value'];
        }
        if (! empty($variant['option2_value'])) {
            $parts[] = $variant['option2_value'];
        }
        if (! empty($variant['option3_value'])) {
            $parts[] = $variant['option3_value'];
        }

        return implode(' / ', $parts) ?: 'Default';
    }

    /**
     * Strip HTML tags and decode entities
     */
    protected function stripHtml(?string $html): ?string
    {
        if (! $html) {
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
     * One-line: variable products get real DB variants + per-variant Stripe product/price.
     */
    protected function importVariableVariants(
        ConnectedProduct $product,
        array $variants,
        string $stripeAccountId,
        array &$errorDetails,
        int &$errorCount
    ): void {
        $variantsProcessed = 0;
        $variantsCreated = 0;
        $variantsUpdated = 0;

        foreach ($variants as $index => $variantData) {
            $variantsProcessed++;

            if (empty($variantData['price'])) {
                continue;
            }

            $priceAmount = $this->parsePrice((string) $variantData['price']);
            if ($priceAmount <= 0) {
                continue;
            }

            $sku = ! empty(trim((string) ($variantData['sku'] ?? ''))) ? trim((string) $variantData['sku']) : null;

            $existingVariant = null;

            if ($sku) {
                $existingVariant = \App\Models\ProductVariant::where('connected_product_id', $product->id)
                    ->where('stripe_account_id', $stripeAccountId)
                    ->where('sku', $sku)
                    ->first();
            }

            if (! $existingVariant) {
                $query = \App\Models\ProductVariant::where('connected_product_id', $product->id)
                    ->where('stripe_account_id', $stripeAccountId);

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
                    'compare_at_price_amount' => ! empty($variantData['compare_at_price'])
                        ? $this->parsePrice((string) $variantData['compare_at_price'])
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
            } else {
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
                        'compare_at_price_amount' => ! empty($variantData['compare_at_price'])
                            ? $this->parsePrice((string) $variantData['compare_at_price'])
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
            }

            // Create Stripe Product + Price per variant
            $createVariantProductAction = app(\App\Actions\ConnectedProducts\CreateVariantProductInStripe::class);
            $variantStripeProductId = $createVariantProductAction($variant);

            if (! $variantStripeProductId) {
                $errorCount++;
                $errorDetails[] = "Stripe variant product create failed for product '{$product->name}' variant idx {$index}";
                continue;
            }

            $createPriceAction = app(CreateConnectedPriceInStripe::class);
            $priceId = $createPriceAction(
                $variantStripeProductId,
                $stripeAccountId,
                $priceAmount,
                'nok',
                [
                    'nickname' => $variant->variant_name ?? $this->buildVariantName($variantData),
                    'metadata' => [
                        'source' => 'shopify-variant',
                        'variant_id' => (string) $variant->id,
                        'sku' => (string) ($variantData['sku'] ?? ''),
                        'barcode' => (string) ($variantData['barcode'] ?? ''),
                    ],
                ]
            );

            if ($priceId) {
                $variant->stripe_product_id = $variantStripeProductId;
                $variant->stripe_price_id = $priceId;
                $variant->saveQuietly();
            } else {
                $errorCount++;
                $errorDetails[] = "Stripe price create failed for '{$product->name}' variant idx {$index}";
            }
        }

        Log::info('Variable variant import summary', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'variants_processed' => $variantsProcessed,
            'variants_created' => $variantsCreated,
            'variants_updated' => $variantsUpdated,
        ]);
    }

    /**
     * One-line: single products use the one CSV price row to set main price; no DB variants are created.
     */
    protected function importSinglePriceOnly(
        ConnectedProduct $product,
        array $variants,
        string $stripeAccountId,
        array &$errorDetails,
        int &$errorCount
    ): void {
        $first = $variants[0] ?? null;

        if (! $first || empty($first['price'])) {
            Log::warning('Single product missing variant price row', [
                'product_id' => $product->id,
                'product_name' => $product->name,
            ]);
            return;
        }

        $priceAmount = $this->parsePrice((string) $first['price']);
        if ($priceAmount <= 0) {
            return;
        }

        if (! $product->stripe_product_id) {
            $errorCount++;
            $errorDetails[] = "Missing stripe_product_id when setting single price for '{$product->name}'";
            return;
        }

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
                    'mode' => 'single',
                ],
            ]
        );

        if (! $priceId) {
            $errorCount++;
            $errorDetails[] = "Stripe price create failed for single '{$product->name}'";
            return;
        }

        if (empty($product->default_price)) {
            $product->default_price = $priceId;
        }

        $product->price = number_format($priceAmount / 100, 2, '.', '');
        $product->currency = 'nok';
        $product->saveQuietly();

        Log::info('Set single product main price', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'default_price' => $product->default_price,
        ]);
    }

    protected function buildParseStats(array $parsed): array
    {
        $products = $parsed['products'] ?? [];

        $vendors = [];
        $types = [];
        $tags = [];
        $categories = [];
        $imagesTotal = 0;
        $variableProducts = 0;
        $singleLikeProducts = 0;

        foreach ($products as $p) {
            $vendor = trim((string) ($p['vendor'] ?? ''));
            if ($vendor !== '') $vendors[$vendor] = true;

            $type = trim((string) ($p['type'] ?? ''));
            if ($type !== '') $types[$type] = true;

            $category = trim((string) ($p['category'] ?? ''));
            if ($category !== '') $categories[$category] = true;

            $rawTags = (string) ($p['tags'] ?? '');
            if ($rawTags !== '') {
                foreach (explode(',', $rawTags) as $t) {
                    $t = trim($t);
                    if ($t !== '') $tags[$t] = true;
                }
            }

            $imagesTotal += is_array($p['images'] ?? null) ? count($p['images']) : 0;

            $vc = (int) ($p['variant_count'] ?? (is_array($p['variants'] ?? null) ? count($p['variants']) : 0));
            if ($vc > 1) $variableProducts++;
            elseif ($vc === 1) $singleLikeProducts++;
        }

        return [
            'total_products' => (int) ($parsed['total_products'] ?? count($products)),
            'total_variants' => (int) ($parsed['total_variants'] ?? 0),
            'variable_products' => $variableProducts,
            'single_like_products' => $singleLikeProducts,
            'unique_vendors' => count($vendors),
            'unique_types' => count($types),
            'unique_tags' => count($tags),
            'unique_categories' => count($categories),
            'total_images' => $imagesTotal,
        ];
    }

    protected function buildProgressBar(int $current, int $total, int $width = 28): string
    {
        if ($total <= 0) return str_repeat('-', $width);

        $ratio = max(0, min(1, $current / $total));
        $filled = (int) floor($ratio * $width);

        return str_repeat('█', $filled) . str_repeat('░', max(0, $width - $filled));
    }

    protected function logProgress(string $channel, int $current, int $total, array $context = []): void
    {
        $bar = $this->buildProgressBar($current, $total);
        $pct = $total > 0 ? (int) floor(($current / $total) * 100) : 0;

        // One-line: reduce noise while still giving high confidence traceability.
        if ($current === 1 || $current === $total || ($current % 10) === 0) {
            Log::info($channel, array_merge($context, [
                'progress' => "{$current}/{$total}",
                'percent' => $pct,
                'bar' => $bar,
            ]));
        }
    }
}
