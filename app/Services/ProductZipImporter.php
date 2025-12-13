<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\ConnectedProduct;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

class ProductZipImporter
{
    protected array $collectionMapping = [];
    protected array $productMapping = [];
    protected ?string $tempDir = null;
    protected array $stats = [
        'collections' => ['imported' => 0, 'updated' => 0, 'skipped' => 0],
        'products' => ['imported' => 0, 'updated' => 0, 'skipped' => 0],
        'variants' => ['imported' => 0, 'updated' => 0],
        'relations' => ['linked' => 0, 'skipped' => 0],
    ];

    public function import(string $zipFilePath, Store $store, bool $update = false, bool $dryRun = false): array
    {
        if (!file_exists($zipFilePath)) {
            throw new \Exception("File not found: {$zipFilePath}");
        }

        if (!$store->stripe_account_id) {
            throw new \Exception("Store does not have a Stripe account ID");
        }

        // Extract zip file - use unique directory name
        // Use sys_get_temp_dir() for better cross-platform support, or storage temp
        $baseTempDir = storage_path('app/temp');
        File::ensureDirectoryExists($baseTempDir);
        
        // Generate unique directory name
        $this->tempDir = $baseTempDir . '/import-' . uniqid('', true) . '-' . bin2hex(random_bytes(4));
        
        // If directory somehow exists, keep generating until we get a unique one
        $maxAttempts = 10;
        $attempts = 0;
        while (is_dir($this->tempDir) && $attempts < $maxAttempts) {
            $this->tempDir = $baseTempDir . '/import-' . uniqid('', true) . '-' . bin2hex(random_bytes(4));
            $attempts++;
        }
        
        if ($attempts >= $maxAttempts) {
            throw new \Exception("Failed to generate unique temporary directory after {$maxAttempts} attempts");
        }
        
        // Use File::ensureDirectoryExists which handles existing directories gracefully
        File::ensureDirectoryExists($this->tempDir, 0755);

        try {
            // Extract zip file
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath) !== true) {
                throw new \Exception("Failed to open zip file: {$zipFilePath}");
            }

            $zip->extractTo($this->tempDir);
            $zip->close();

            // Read manifest
            $manifestPath = $this->tempDir . '/manifest.json';
            if (!file_exists($manifestPath)) {
                throw new \Exception("Manifest file not found in zip");
            }

            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (!$manifest) {
                throw new \Exception("Failed to parse manifest file");
            }

            // Import collections first
            $this->importCollections($manifest['collections'] ?? [], $store, $update, $dryRun);

            // Import products
            $this->importProducts($manifest['products'] ?? [], $store, $update, $dryRun);

            // Import product-collection relationships
            $this->importProductCollectionRelations($manifest['product_collection_relations'] ?? [], $dryRun);

            // Clean up
            File::deleteDirectory($this->tempDir);

            return [
                'success' => true,
                'stats' => $this->stats,
                'export_date' => $manifest['export_date'] ?? null,
            ];
        } catch (\Exception $e) {
            // Clean up temp directory on error
            if ($this->tempDir && File::exists($this->tempDir)) {
                File::deleteDirectory($this->tempDir);
            }
            
            throw $e;
        }
    }

    public function preview(string $zipFilePath): array
    {
        if (!file_exists($zipFilePath)) {
            throw new \Exception("File not found: {$zipFilePath}");
        }

        // Extract zip file - use unique directory name
        // Use sys_get_temp_dir() for better cross-platform support, or storage temp
        $baseTempDir = storage_path('app/temp');
        File::ensureDirectoryExists($baseTempDir);
        
        // Generate unique directory name
        $this->tempDir = $baseTempDir . '/preview-' . uniqid('', true) . '-' . bin2hex(random_bytes(4));
        
        // If directory somehow exists, keep generating until we get a unique one
        $maxAttempts = 10;
        $attempts = 0;
        while (is_dir($this->tempDir) && $attempts < $maxAttempts) {
            $this->tempDir = $baseTempDir . '/preview-' . uniqid('', true) . '-' . bin2hex(random_bytes(4));
            $attempts++;
        }
        
        if ($attempts >= $maxAttempts) {
            throw new \Exception("Failed to generate unique temporary directory after {$maxAttempts} attempts");
        }
        
        // Use File::ensureDirectoryExists which handles existing directories gracefully
        File::ensureDirectoryExists($this->tempDir, 0755);

        try {
            // Extract zip file
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath) !== true) {
                throw new \Exception("Failed to open zip file: {$zipFilePath}");
            }

            $zip->extractTo($this->tempDir);
            $zip->close();

            // Read manifest
            $manifestPath = $this->tempDir . '/manifest.json';
            if (!file_exists($manifestPath)) {
                throw new \Exception("Manifest file not found in zip");
            }

            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (!$manifest) {
                throw new \Exception("Failed to parse manifest file");
            }

            // Clean up
            File::deleteDirectory($this->tempDir);

            return [
                'export_date' => $manifest['export_date'] ?? null,
                'collections_count' => count($manifest['collections'] ?? []),
                'products_count' => count($manifest['products'] ?? []),
                'relations_count' => count($manifest['product_collection_relations'] ?? []),
                'collections' => array_slice($manifest['collections'] ?? [], 0, 10), // Preview first 10
                'products' => array_slice($manifest['products'] ?? [], 0, 10), // Preview first 10
            ];
        } catch (\Exception $e) {
            // Clean up temp directory on error
            if ($this->tempDir && File::exists($this->tempDir)) {
                File::deleteDirectory($this->tempDir);
            }
            
            throw $e;
        }
    }

    protected function importCollections(array $collections, Store $store, bool $update, bool $dryRun): void
    {
        foreach ($collections as $collectionData) {
            $name = $collectionData['name'] ?? null;
            if (!$name) {
                continue;
            }

            // Find existing collection by name
            $existing = Collection::where('name', $name)
                ->where('stripe_account_id', $store->stripe_account_id)
                ->first();

            if ($existing && !$update) {
                $this->collectionMapping[$collectionData['id']] = $existing->id;
                $this->stats['collections']['skipped']++;
                continue;
            }

            if ($dryRun) {
                if (!$existing) {
                    $this->collectionMapping[$collectionData['id']] = 'new-id';
                } else {
                    $this->collectionMapping[$collectionData['id']] = $existing->id;
                }
                $this->stats['collections'][$existing ? 'updated' : 'imported']++;
                continue;
            }

            $collection = $existing ?? new Collection();
            $collection->store_id = $store->id;
            $collection->stripe_account_id = $store->stripe_account_id;
            $collection->name = $name;
            $collection->description = $collectionData['description'] ?? null;
            $collection->handle = $collectionData['handle'] ?? Str::slug($name);
            $collection->active = $collectionData['active'] ?? true;
            $collection->sort_order = $collectionData['sort_order'] ?? 0;
            $collection->metadata = $collectionData['metadata'] ?? [];

            // Handle collection image
            if (isset($collectionData['image_path'])) {
                $imagePath = $this->copyCollectionImage($collectionData['image_path'], $collection->handle);
                if ($imagePath) {
                    $collection->image_url = $imagePath;
                }
            } elseif (isset($collectionData['image_url']) && !str_contains($collectionData['image_url'], '/storage/')) {
                // External URL - keep as is
                $collection->image_url = $collectionData['image_url'];
            }

            $collection->save();

            $this->collectionMapping[$collectionData['id']] = $collection->id;
            $this->stats['collections'][$existing ? 'updated' : 'imported']++;
        }
    }

    protected function importProducts(array $products, Store $store, bool $update, bool $dryRun): void
    {
        foreach ($products as $productData) {
            $name = $productData['name'] ?? null;
            if (!$name) {
                continue;
            }

            // Find existing product by name
            $existing = ConnectedProduct::where('name', $name)
                ->where('stripe_account_id', $store->stripe_account_id)
                ->first();

            if ($existing && !$update) {
                $this->productMapping[$productData['id']] = $existing->id;
                $this->stats['products']['skipped']++;
                continue;
            }

            if ($dryRun) {
                if (!$existing) {
                    $this->productMapping[$productData['id']] = 'new-id';
                } else {
                    $this->productMapping[$productData['id']] = $existing->id;
                }
                $this->stats['products'][$existing ? 'updated' : 'imported']++;
                continue;
            }

            $product = $existing ?? new ConnectedProduct();
            $product->stripe_account_id = $store->stripe_account_id;
            $product->name = $name;
            $product->description = $productData['description'] ?? null;
            $product->active = $productData['active'] ?? true;
            $product->type = $productData['type'] ?? null;
            $product->url = $productData['url'] ?? null;
            $product->package_dimensions = $productData['package_dimensions'] ?? null;
            $product->shippable = $productData['shippable'] ?? false;
            $product->no_price_in_pos = $productData['no_price_in_pos'] ?? false;
            $product->statement_descriptor = $productData['statement_descriptor'] ?? null;
            $product->tax_code = $productData['tax_code'] ?? null;
            $product->unit_label = $productData['unit_label'] ?? null;
            $product->price = $productData['price'] ?? null;
            $product->currency = $productData['currency'] ?? 'nok';
            $product->compare_at_price_amount = $productData['compare_at_price_amount'] ?? null;
            $product->article_group_code = $productData['article_group_code'] ?? null;
            $product->product_code = $productData['product_code'] ?? null;
            $product->product_meta = $productData['product_meta'] ?? [];
            $product->images = $productData['images'] ?? [];
            $product->vendor_id = $productData['vendor_id'] ?? null;

            // Only set Stripe IDs if they were included in export
            if (isset($productData['stripe_product_id']) && $productData['stripe_product_id']) {
                $product->stripe_product_id = $productData['stripe_product_id'];
            }
            if (isset($productData['default_price']) && $productData['default_price']) {
                $product->default_price = $productData['default_price'];
            }

            $product->save();

            // Import product images
            if (isset($productData['media_files']) && is_array($productData['media_files'])) {
                foreach ($productData['media_files'] as $mediaFile) {
                    if (isset($mediaFile['path'])) {
                        $sourcePath = $this->tempDir . '/media/' . $mediaFile['path'];
                        if (file_exists($sourcePath)) {
                            try {
                                $product->addMedia($sourcePath)
                                    ->usingName($mediaFile['name'] ?? $mediaFile['file_name'])
                                    ->usingFileName($mediaFile['file_name'])
                                    ->toMediaCollection('images');
                            } catch (\Exception $e) {
                                // Silently fail on image import
                            }
                        }
                    }
                }
            }

            // Import variants
            if (isset($productData['variants']) && is_array($productData['variants'])) {
                $this->importVariants($product, $productData['variants'], $store, $update, $dryRun);
            }

            $this->productMapping[$productData['id']] = $product->id;
            $this->stats['products'][$existing ? 'updated' : 'imported']++;
        }
    }

    protected function importVariants(ConnectedProduct $product, array $variants, Store $store, bool $update, bool $dryRun): void
    {
        foreach ($variants as $variantData) {
            if ($dryRun) {
                $this->stats['variants']['imported']++;
                continue;
            }

            // Find existing variant by SKU or create new
            $existing = null;
            if (isset($variantData['sku']) && $variantData['sku']) {
                $existing = ProductVariant::where('sku', $variantData['sku'])
                    ->where('stripe_account_id', $store->stripe_account_id)
                    ->first();
            }

            $variant = $existing ?? new ProductVariant();
            $variant->connected_product_id = $product->id;
            $variant->stripe_account_id = $store->stripe_account_id;
            $variant->sku = $variantData['sku'] ?? null;
            $variant->barcode = $variantData['barcode'] ?? null;
            $variant->option1_name = $variantData['option1_name'] ?? null;
            $variant->option1_value = $variantData['option1_value'] ?? null;
            $variant->option2_name = $variantData['option2_name'] ?? null;
            $variant->option2_value = $variantData['option2_value'] ?? null;
            $variant->option3_name = $variantData['option3_name'] ?? null;
            $variant->option3_value = $variantData['option3_value'] ?? null;
            $variant->price_amount = $variantData['price_amount'] ?? null;
            $variant->currency = $variantData['currency'] ?? 'nok';
            $variant->compare_at_price_amount = $variantData['compare_at_price_amount'] ?? null;
            $variant->weight_grams = $variantData['weight_grams'] ?? null;
            $variant->requires_shipping = $variantData['requires_shipping'] ?? false;
            $variant->taxable = $variantData['taxable'] ?? true;
            $variant->inventory_quantity = $variantData['inventory_quantity'] ?? null;
            $variant->inventory_policy = $variantData['inventory_policy'] ?? null;
            $variant->inventory_management = $variantData['inventory_management'] ?? null;
            $variant->active = $variantData['active'] ?? true;
            $variant->no_price_in_pos = $variantData['no_price_in_pos'] ?? false;
            $variant->metadata = $variantData['metadata'] ?? [];

            // Only set Stripe IDs if they were included in export
            if (isset($variantData['stripe_product_id']) && $variantData['stripe_product_id']) {
                $variant->stripe_product_id = $variantData['stripe_product_id'];
            }
            if (isset($variantData['stripe_price_id']) && $variantData['stripe_price_id']) {
                $variant->stripe_price_id = $variantData['stripe_price_id'];
            }

            // Handle variant image
            if (isset($variantData['image_path'])) {
                $imagePath = $this->copyVariantImage($variantData['image_path'], $product->id, $variant->sku);
                if ($imagePath) {
                    $variant->image_url = $imagePath;
                }
            } elseif (isset($variantData['image_url']) && !str_contains($variantData['image_url'], '/storage/')) {
                // External URL - keep as is
                $variant->image_url = $variantData['image_url'];
            }

            $variant->save();
            $this->stats['variants'][$existing ? 'updated' : 'imported']++;
        }
    }

    protected function importProductCollectionRelations(array $relations, bool $dryRun): void
    {
        foreach ($relations as $relation) {
            $productName = $relation['product_name'] ?? null;
            $collectionName = $relation['collection_name'] ?? null;

            if (!$productName || !$collectionName) {
                continue;
            }

            // Find product and collection by name
            $product = ConnectedProduct::where('name', $productName)->first();
            $collection = Collection::where('name', $collectionName)->first();

            if (!$product || !$collection) {
                $this->stats['relations']['skipped']++;
                continue;
            }

            if ($dryRun) {
                $this->stats['relations']['linked']++;
                continue;
            }

            // Check if relation already exists
            if (!$product->collections()->where('collections.id', $collection->id)->exists()) {
                $product->collections()->attach($collection->id, [
                    'sort_order' => $relation['sort_order'] ?? 0,
                ]);
                $this->stats['relations']['linked']++;
            } else {
                $this->stats['relations']['skipped']++;
            }
        }
    }

    protected function copyCollectionImage(string $imagePath, string $collectionHandle): ?string
    {
        if (!$this->tempDir) {
            return null;
        }
        $sourcePath = $this->tempDir . '/media/' . $imagePath;
        if (!file_exists($sourcePath)) {
            return null;
        }

        $fileName = basename($imagePath);
        $destPath = 'collections/' . $collectionHandle . '-' . $fileName;
        $fullDestPath = storage_path('app/public/' . $destPath);

        File::ensureDirectoryExists(dirname($fullDestPath), 0755);
        File::copy($sourcePath, $fullDestPath);

        return '/storage/' . $destPath;
    }

    protected function copyVariantImage(string $imagePath, int $productId, ?string $sku): ?string
    {
        if (!$this->tempDir) {
            return null;
        }
        $sourcePath = $this->tempDir . '/media/' . $imagePath;
        if (!file_exists($sourcePath)) {
            return null;
        }

        $fileName = basename($imagePath);
        $prefix = $sku ? Str::slug($sku) : "product-{$productId}";
        $destPath = 'variants/' . $prefix . '-' . $fileName;
        $fullDestPath = storage_path('app/public/' . $destPath);

        File::ensureDirectoryExists(dirname($fullDestPath), 0755);
        File::copy($sourcePath, $fullDestPath);

        return '/storage/' . $destPath;
    }
}
