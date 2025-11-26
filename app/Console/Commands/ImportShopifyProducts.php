<?php

namespace App\Console\Commands;

use App\Models\ConnectedProduct;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ImportShopifyProducts extends Command
{
    protected $signature = 'products:import-shopify 
                            {urls* : Product URLs to import}
                            {--store= : Store slug or ID to associate products with}
                            {--dry-run : Show what would be imported without actually importing}
                            {--update : Update existing products instead of skipping them}';

    protected $description = 'Import products from Shopify store URLs into ConnectedProduct model';

    public function handle(): int
    {
        $urls = $this->argument('urls');
        $storeSlug = $this->option('store');
        $dryRun = $this->option('dry-run');
        $update = $this->option('update');

        // Get store
        $store = null;
        if ($storeSlug) {
            // Try slug first, then ID
            $store = Store::where('slug', $storeSlug)->first();
            if (!$store && is_numeric($storeSlug)) {
                $store = Store::where('id', $storeSlug)->first();
            }
            
            if (!$store) {
                $this->error("Store not found: {$storeSlug}");
                return 1;
            }
            
            if (!$store->stripe_account_id) {
                $this->error("Store does not have a Stripe account ID");
                return 1;
            }
        } else {
            // Get first store with Stripe account
            $store = Store::whereNotNull('stripe_account_id')->first();
            
            if (!$store) {
                $this->error("No store with Stripe account found. Please specify --store option.");
                return 1;
            }
        }

        $this->info("Using store: {$store->name} (ID: {$store->id})");
        $this->info("Stripe Account: {$store->stripe_account_id}");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No products will be created");
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($urls as $url) {
            try {
                $this->info("\nProcessing: {$url}");
                
                $productData = $this->fetchProductData($url);
                
                if (!$productData) {
                    $this->error("Failed to fetch product data from: {$url}");
                    $errors++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("Would import:");
                    $this->line("  Name: {$productData['name']}");
                    $this->line("  Price: " . ($productData['price'] ?? 'N/A'));
                    $this->line("  Currency: " . ($productData['currency'] ?? 'NOK'));
                    $this->line("  Images: " . count($productData['images'] ?? []));
                    $this->line("  Description: " . Str::limit($productData['description'] ?? 'N/A', 100));
                    continue;
                }

                // Check if product already exists
                $existing = ConnectedProduct::where('name', $productData['name'])
                    ->where('stripe_account_id', $store->stripe_account_id)
                    ->first();

                if ($existing && !$update) {
                    $this->warn("Product already exists: {$productData['name']} (ID: {$existing->id})");
                    $skipped++;
                    continue;
                }
                
                // If updating, use existing product
                if ($existing && $update) {
                    $product = $existing;
                    $this->info("  Updating existing product: {$product->name} (ID: {$product->id})");
                    
                    // Update product fields
                    $product->description = $productData['description'] ?? $product->description;
                    $product->url = $url;
                    if (!is_array($product->product_meta)) {
                        $product->product_meta = [];
                    }
                    $product->product_meta = array_merge($product->product_meta, [
                        'source' => 'shopify',
                        'source_url' => $url,
                        'original_price' => $productData['price'] ?? null,
                    ]);
                    $product->save();
                } else {
                    // Create new product
                    $product = new ConnectedProduct([
                        'stripe_account_id' => $store->stripe_account_id,
                        'name' => $productData['name'],
                        'description' => $productData['description'] ?? null,
                        'active' => true,
                        'type' => 'good', // Physical product
                        'url' => $url,
                        'shippable' => true,
                        'product_meta' => [
                            'source' => 'shopify',
                            'source_url' => $url,
                            'original_price' => $productData['price'] ?? null,
                        ],
                    ]);

                    // Create product in Stripe first
                    $createAction = new \App\Actions\ConnectedProducts\CreateConnectedProductInStripe();
                    $stripeProductId = $createAction($product);

                    if (!$stripeProductId) {
                        $this->error("Failed to create product in Stripe: {$productData['name']}");
                        $errors++;
                        continue;
                    }

                    // Set the Stripe product ID and save
                    $product->stripe_product_id = $stripeProductId;
                    $product->save();
                }

                // Download and add images (clear existing if updating)
                if ($update && $existing) {
                    // Clear existing images if updating
                    $product->clearMediaCollection('images');
                    $this->info("  Cleared existing images");
                }
                if (!empty($productData['images'])) {
                    $this->info("  Downloading " . count($productData['images']) . " image(s)...");
                    $imageCount = $this->downloadAndAddImages($product, $productData['images']);
                    $this->info("  ✓ Added {$imageCount} image(s)");
                }

                // Create price if available
                if (!empty($productData['price']) && !empty($productData['currency'])) {
                    $this->info("  Creating price...");
                    $this->line("  Price data: {$productData['price']} {$productData['currency']}");
                    $priceAmount = $this->parsePrice($productData['price'], $productData['currency']);
                    
                    if ($priceAmount > 0) {
                        // Check if price already exists
                        $existingPrice = \App\Models\ConnectedPrice::where('stripe_product_id', $product->stripe_product_id)
                            ->where('stripe_account_id', $store->stripe_account_id)
                            ->where('unit_amount', $priceAmount)
                            ->where('currency', strtolower($productData['currency']))
                            ->first();
                        
                        if ($existingPrice && $update) {
                            $this->info("  Price already exists: " . number_format($priceAmount / 100, 2) . " " . strtoupper($productData['currency']));
                        } else {
                            $createPriceAction = new \App\Actions\ConnectedPrices\CreateConnectedPriceInStripe();
                            $stripePriceId = $createPriceAction(
                                $product->stripe_product_id,
                                $store->stripe_account_id,
                                $priceAmount,
                                $productData['currency'],
                                [
                                    'metadata' => [
                                        'source' => 'shopify',
                                        'source_url' => $url,
                                    ],
                                ]
                            );
                            
                            if ($stripePriceId) {
                                $this->info("  ✓ Created price: " . number_format($priceAmount / 100, 2) . " " . strtoupper($productData['currency']));
                            } else {
                                $this->warn("  ⚠ Failed to create price (amount: {$priceAmount}, currency: {$productData['currency']})");
                            }
                        }
                    } else {
                        $this->warn("  ⚠ Invalid price amount: {$priceAmount} (parsed from: {$productData['price']})");
                    }
                } else {
                    $this->warn("  ⚠ No price data found (price: " . ($productData['price'] ?? 'null') . ", currency: " . ($productData['currency'] ?? 'null') . ")");
                }

                // Upload images to Stripe and sync product
                if ($product->hasMedia('images')) {
                    $this->info("  Uploading images to Stripe...");
                    $uploadAction = new \App\Actions\ConnectedProducts\UploadProductImagesToStripe();
                    $imageUrls = $uploadAction($product);
                    
                    if (!empty($imageUrls)) {
                        $product->images = $imageUrls;
                        $product->saveQuietly(); // Save without triggering events
                        
                        // Update product in Stripe with images
                        $updateAction = new \App\Actions\ConnectedProducts\UpdateConnectedProductToStripe();
                        $updateAction($product);
                        $this->info("  ✓ Uploaded " . count($imageUrls) . " image(s) to Stripe");
                    }
                }

                $stripeProductId = $product->stripe_product_id;
                $action = ($update && isset($existing) && $existing) ? 'Updated' : 'Imported';
                $this->info("✓ {$action}: {$product->name} (ID: {$product->id}, Stripe: {$stripeProductId})");
                $imported++;

            } catch (\Exception $e) {
                $this->error("Error importing {$url}: {$e->getMessage()}");
                $this->error("Stack trace: " . $e->getTraceAsString());
                $errors++;
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Imported: {$imported}");
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: {$errors}");

        return 0;
    }

    protected function fetchProductData(string $url): ?array
    {
        try {
            $response = Http::timeout(30)->get($url);
            
            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();
            $productData = [
                'name' => null,
                'description' => null,
                'price' => null,
                'currency' => 'nok', // Default to NOK
                'images' => [],
            ];
            
            // Extract product data from JSON-LD (Shopify's structured data)
            preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $jsonMatches);
            
            foreach ($jsonMatches[1] as $jsonString) {
                $jsonData = json_decode($jsonString, true);
                
                if ($jsonData && isset($jsonData['@type'])) {
                    if ($jsonData['@type'] === 'Product') {
                        $productData['name'] = $jsonData['name'] ?? $productData['name'];
                        $productData['description'] = $jsonData['description'] ?? $productData['description'];
                        
                        if (isset($jsonData['offers'])) {
                            $offers = is_array($jsonData['offers']) ? $jsonData['offers'] : [$jsonData['offers']];
                            foreach ($offers as $offer) {
                                if (isset($offer['price'])) {
                                    $productData['price'] = $offer['price'];
                                    $productData['currency'] = strtolower($offer['priceCurrency'] ?? 'nok');
                                }
                            }
                        }
                        
                        // Extract images
                        if (isset($jsonData['image'])) {
                            $images = is_array($jsonData['image']) ? $jsonData['image'] : [$jsonData['image']];
                            foreach ($images as $image) {
                                if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
                                    $productData['images'][] = $image;
                                } elseif (is_array($image)) {
                                    // Handle ImageObject with url property
                                    if (isset($image['url']) && filter_var($image['url'], FILTER_VALIDATE_URL)) {
                                        $productData['images'][] = $image['url'];
                                    }
                                    // Handle direct image URL in array
                                    elseif (isset($image[0]) && is_string($image[0]) && filter_var($image[0], FILTER_VALIDATE_URL)) {
                                        $productData['images'][] = $image[0];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Also try to extract from Shopify's product JSON (often in a script tag)
            preg_match('/<script id="ProductJson-(.*?)" type="application\/json">(.*?)<\/script>/s', $html, $productJsonMatch);
            if (isset($productJsonMatch[2])) {
                $shopifyData = json_decode($productJsonMatch[2], true);
                if ($shopifyData) {
                    $productData['name'] = $shopifyData['title'] ?? $productData['name'];
                    $productData['description'] = $shopifyData['description'] ?? $productData['description'];
                    
                    // Get price from variants
                    if (isset($shopifyData['variants']) && is_array($shopifyData['variants']) && !empty($shopifyData['variants'])) {
                        $variant = $shopifyData['variants'][0];
                        if (isset($variant['price'])) {
                            $productData['price'] = $variant['price'];
                        }
                    }
                    
                    // Get images
                    if (isset($shopifyData['images']) && is_array($shopifyData['images'])) {
                        foreach ($shopifyData['images'] as $image) {
                            if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
                                $productData['images'][] = $image;
                            } elseif (is_array($image)) {
                                // Try 'src' property
                                if (isset($image['src']) && filter_var($image['src'], FILTER_VALIDATE_URL)) {
                                    $productData['images'][] = $image['src'];
                                }
                                // Try 'url' property
                                elseif (isset($image['url']) && filter_var($image['url'], FILTER_VALIDATE_URL)) {
                                    $productData['images'][] = $image['url'];
                                }
                            }
                        }
                    }
                }
            }

            // Fallback: Try to extract from meta tags
            if (!$productData['name']) {
                preg_match('/<meta property="og:title" content="(.*?)"/', $html, $titleMatch);
                if ($titleMatch) {
                    $productData['name'] = html_entity_decode($titleMatch[1]);
                }
            }
            
            if (!$productData['description']) {
                preg_match('/<meta property="og:description" content="(.*?)"/', $html, $descMatch);
                if ($descMatch) {
                    $productData['description'] = html_entity_decode($descMatch[1]);
                }
            }
            
            if (!$productData['price']) {
                preg_match('/<meta property="product:price:amount" content="(.*?)"/', $html, $priceMatch);
                if ($priceMatch) {
                    $productData['price'] = $priceMatch[1];
                }
            }
            
            // Extract images from meta tags
            if (empty($productData['images'])) {
                preg_match('/<meta property="og:image" content="(.*?)"/', $html, $imageMatch);
                if ($imageMatch && filter_var($imageMatch[1], FILTER_VALIDATE_URL)) {
                    $productData['images'][] = $imageMatch[1];
                }
            }
            
            // Also try to extract price from visible price elements on the page
            if (!$productData['price']) {
                // Look for price patterns in the HTML
                preg_match('/"price":\s*"([\d.,]+)"/', $html, $priceJsonMatch);
                if ($priceJsonMatch) {
                    $productData['price'] = $priceJsonMatch[1];
                }
            }

            // Last resort: Extract from page title
            if (!$productData['name']) {
                preg_match('/<title>(.*?)<\/title>/', $html, $titleMatch);
                if ($titleMatch) {
                    $productData['name'] = html_entity_decode(strip_tags($titleMatch[1]));
                }
            }

            // Remove duplicates and invalid URLs from images
            $productData['images'] = array_values(array_unique(array_filter($productData['images'], function($url) {
                return filter_var($url, FILTER_VALIDATE_URL) !== false;
            })));
            
            // Limit to first 8 images
            $productData['images'] = array_slice($productData['images'], 0, 8);

            return $productData['name'] ? $productData : null;
        } catch (\Exception $e) {
            $this->error("Error fetching product data: {$e->getMessage()}");
            Log::error('Failed to fetch product data', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function parsePrice(string $price, string $currency = 'nok'): int
    {
        // Remove currency symbols and whitespace
        $price = preg_replace('/[^\d.,]/', '', $price);
        
        // Handle Norwegian format (1.234,56) or US format (1,234.56)
        if (strpos($price, ',') !== false && strpos($price, '.') !== false) {
            // Determine which is decimal separator
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

    protected function downloadAndAddImages(ConnectedProduct $product, array $imageUrls): int
    {
        $added = 0;
        
        foreach ($imageUrls as $imageUrl) {
            try {
                // Use Spatie Media Library's addMediaFromUrl method
                // This handles downloading, storing, and adding to the collection
                $product->addMediaFromUrl($imageUrl)
                    ->toMediaCollection('images');
                
                $added++;
            } catch (\Exception $e) {
                $this->warn("  ⚠ Failed to add image {$imageUrl}: {$e->getMessage()}");
                Log::warning('Failed to download and add product image', [
                    'product_id' => $product->id,
                    'image_url' => $imageUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $added;
    }
}

