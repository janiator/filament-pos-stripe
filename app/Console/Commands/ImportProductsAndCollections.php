<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\ConnectedProduct;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ImportProductsAndCollections extends Command
{
    protected $signature = 'products:import 
                            {file : Path to the export zip file}
                            {--store= : Store slug or ID to import products to}
                            {--update : Update existing products/collections instead of skipping}
                            {--dry-run : Show what would be imported without actually importing}';

    protected $description = 'Import products, collections, variants, and assets from a zip file';

    protected array $collectionMapping = [];
    protected array $productMapping = [];
    protected ?string $tempDir = null;

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $storeSlug = $this->option('store');
        $update = $this->option('update');
        $dryRun = $this->option('dry-run');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        // Get store
        $store = null;
        if ($storeSlug) {
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
                $this->error("No store found. Please specify --store option.");
                return 1;
            }
        }

        $this->info("Importing to store: {$store->name} (ID: {$store->id})");
        $this->info("Stripe Account: {$store->stripe_account_id}");

        if ($dryRun) {
            $this->warn("DRY RUN MODE - No data will be imported");
        }

        // Extract zip file
        $this->tempDir = storage_path('app/temp/import-' . time());
        File::makeDirectory($this->tempDir, 0755, true);

        try {
            $this->info("\nExtracting zip file...");
            $zip = new ZipArchive();
            if ($zip->open($filePath) !== true) {
                $this->error("Failed to open zip file: {$filePath}");
                return 1;
            }

            $zip->extractTo($tempDir);
            $zip->close();

            // Read manifest
            $manifestPath = $this->tempDir . '/manifest.json';
            if (!file_exists($manifestPath)) {
                $this->error("Manifest file not found in zip");
                return 1;
            }

            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (!$manifest) {
                $this->error("Failed to parse manifest file");
                return 1;
            }

            $this->info("Export date: " . ($manifest['export_date'] ?? 'Unknown'));
            $this->info("Collections to import: " . count($manifest['collections'] ?? []));
            $this->info("Products to import: " . count($manifest['products'] ?? []));

            // Import collections first
            $this->info("\nImporting collections...");
            $this->importCollections($manifest['collections'] ?? [], $store, $update, $dryRun);

            // Import products
            $this->info("\nImporting products...");
            $this->importProducts($manifest['products'] ?? [], $store, $update, $dryRun);

            // Import product-collection relationships
            $this->info("\nLinking products to collections...");
            $this->importProductCollectionRelations($manifest['product_collection_relations'] ?? [], $dryRun);

            // Clean up
            File::deleteDirectory($this->tempDir);

            $this->info("\n✅ Import completed successfully!");

            return 0;
        } catch (\Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            
            // Clean up temp directory on error
            if ($this->tempDir && File::exists($this->tempDir)) {
                File::deleteDirectory($this->tempDir);
            }
            
            return 1;
        }
    }

    protected function importCollections(array $collections, Store $store, bool $update, bool $dryRun): void
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($collections as $collectionData) {
            $name = $collectionData['name'] ?? null;
            if (!$name) {
                $this->warn("Skipping collection without name");
                continue;
            }

            // Find existing collection by name
            $existing = Collection::where('name', $name)
                ->where('stripe_account_id', $store->stripe_account_id)
                ->first();

            if ($existing && !$update) {
                $this->line("  ⏭ Skipping existing collection: {$name}");
                $this->collectionMapping[$collectionData['id']] = $existing->id;
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  Would " . ($existing ? "update" : "create") . " collection: {$name}");
                if (!$existing) {
                    $this->collectionMapping[$collectionData['id']] = 'new-id';
                } else {
                    $this->collectionMapping[$collectionData['id']] = $existing->id;
                }
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
            if (isset($collectionData['image_path']) && !$dryRun) {
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
            
            if ($existing) {
                $this->line("  ✓ Updated collection: {$name}");
                $updated++;
            } else {
                $this->line("  ✓ Created collection: {$name}");
                $imported++;
            }
        }

        if (!$dryRun) {
            $this->info("Collections: {$imported} created, {$updated} updated, {$skipped} skipped");
        }
    }

    protected function importProducts(array $products, Store $store, bool $update, bool $dryRun): void
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($products as $productData) {
            $name = $productData['name'] ?? null;
            if (!$name) {
                $this->warn("Skipping product without name");
                continue;
            }

            // Find existing product by name
            $existing = ConnectedProduct::where('name', $name)
                ->where('stripe_account_id', $store->stripe_account_id)
                ->first();

            if ($existing && !$update) {
                $this->line("  ⏭ Skipping existing product: {$name}");
                $this->productMapping[$productData['id']] = $existing->id;
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  Would " . ($existing ? "update" : "create") . " product: {$name}");
                if (!$existing) {
                    $this->productMapping[$productData['id']] = 'new-id';
                } else {
                    $this->productMapping[$productData['id']] = $existing->id;
                }
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
                                $this->warn("  ⚠ Failed to add image {$mediaFile['file_name']}: {$e->getMessage()}");
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

            if ($existing) {
                $this->line("  ✓ Updated product: {$name}");
                $updated++;
            } else {
                $this->line("  ✓ Created product: {$name}");
                $imported++;
            }
        }

        if (!$dryRun) {
            $this->info("Products: {$imported} created, {$updated} updated, {$skipped} skipped");
        }
    }

    protected function importVariants(ConnectedProduct $product, array $variants, Store $store, bool $update, bool $dryRun): void
    {
        foreach ($variants as $variantData) {
            if ($dryRun) {
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
            if (isset($variantData['image_path']) && !$dryRun) {
                $imagePath = $this->copyVariantImage($variantData['image_path'], $product->id, $variant->sku);
                if ($imagePath) {
                    $variant->image_url = $imagePath;
                }
            } elseif (isset($variantData['image_url']) && !str_contains($variantData['image_url'], '/storage/')) {
                // External URL - keep as is
                $variant->image_url = $variantData['image_url'];
            }

            $variant->save();
        }
    }

    protected function importProductCollectionRelations(array $relations, bool $dryRun): void
    {
        $linked = 0;
        $skipped = 0;

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
                $this->warn("  ⚠ Could not link {$productName} to {$collectionName} (not found)");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  Would link product '{$productName}' to collection '{$collectionName}'");
                continue;
            }

            // Check if relation already exists
            if (!$product->collections()->where('collections.id', $collection->id)->exists()) {
                $product->collections()->attach($collection->id, [
                    'sort_order' => $relation['sort_order'] ?? 0,
                ]);
                $linked++;
            } else {
                $skipped++;
            }
        }

        if (!$dryRun) {
            $this->info("Product-Collection links: {$linked} created, {$skipped} skipped");
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

        File::makeDirectory(dirname($fullDestPath), 0755, true);
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

        File::makeDirectory(dirname($fullDestPath), 0755, true);
        File::copy($sourcePath, $fullDestPath);

        return '/storage/' . $destPath;
    }
}

