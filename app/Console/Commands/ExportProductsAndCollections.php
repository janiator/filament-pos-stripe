<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\ConnectedProduct;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ExportProductsAndCollections extends Command
{
    protected $signature = 'products:export 
                            {--store= : Store slug or ID to export products from}
                            {--output= : Output file path (default: products-export-{timestamp}.zip)}
                            {--include-stripe-ids : Include Stripe IDs in export (not recommended for cross-server transfer)}';

    protected $description = 'Export products, collections, variants, and assets to a zip file';

    public function handle(): int
    {
        $storeSlug = $this->option('store');
        $outputPath = $this->option('output');
        $includeStripeIds = $this->option('include-stripe-ids');

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
        } else {
            // Get first store with Stripe account
            $store = Store::whereNotNull('stripe_account_id')->first();
            
            if (!$store) {
                $this->error("No store found. Please specify --store option.");
                return 1;
            }
        }

        $this->info("Exporting from store: {$store->name} (ID: {$store->id})");
        $this->info("Stripe Account: {$store->stripe_account_id}");

        // Create temporary directory for export
        $tempDir = storage_path('app/temp/export-' . time());
        if (!File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }
        $mediaDir = $tempDir . '/media';
        if (!File::exists($mediaDir)) {
            File::makeDirectory($mediaDir, 0755, true);
        }

        try {
            // Export collections
            $this->info("\nExporting collections...");
            $collections = Collection::where('stripe_account_id', $store->stripe_account_id)->get();
            $collectionsData = [];
            
            foreach ($collections as $collection) {
                $collectionData = $collection->toArray();
                
                // Handle collection image
                $imagePath = null;
                if ($collection->image_url) {
                    $imagePath = $this->copyCollectionImage($collection, $mediaDir);
                    if ($imagePath) {
                        $collectionData['image_path'] = $imagePath;
                    }
                }
                
                // Don't include Stripe IDs unless requested
                if (!$includeStripeIds) {
                    unset($collectionData['stripe_account_id']);
                }
                
                $collectionsData[] = $collectionData;
            }
            
            $this->info("Exported {$collections->count()} collections");

            // Export products
            $this->info("\nExporting products...");
            $products = ConnectedProduct::where('stripe_account_id', $store->stripe_account_id)->get();
            $productsData = [];
            $productCollectionRelations = [];
            
            foreach ($products as $product) {
                $productData = $product->toArray();
                
                // Export product images (Spatie Media Library)
                $productImages = [];
                $mediaItems = $product->getMedia('images');
                foreach ($mediaItems as $media) {
                    $mediaPath = $this->copyMediaFile($media, $mediaDir, "product-{$product->id}");
                    if ($mediaPath) {
                        $productImages[] = [
                            'file_name' => $media->file_name,
                            'name' => $media->name,
                            'mime_type' => $media->mime_type,
                            'size' => $media->size,
                            'path' => $mediaPath,
                        ];
                    }
                }
                $productData['media_files'] = $productImages;
                
                // Export variants
                $variants = $product->variants()->get();
                $variantsData = [];
                foreach ($variants as $variant) {
                    $variantData = $variant->toArray();
                    
                    // Handle variant image
                    $variantImagePath = null;
                    if ($variant->image_url) {
                        $variantImagePath = $this->copyVariantImage($variant, $mediaDir, "variant-{$variant->id}");
                        if ($variantImagePath) {
                            $variantData['image_path'] = $variantImagePath;
                        }
                    }
                    
                    // Don't include Stripe IDs unless requested
                    if (!$includeStripeIds) {
                        unset($variantData['stripe_product_id']);
                        unset($variantData['stripe_price_id']);
                        unset($variantData['stripe_account_id']);
                    }
                    
                    $variantsData[] = $variantData;
                }
                $productData['variants'] = $variantsData;
                
                // Export collection relationships
                $collections = $product->collections()->get();
                foreach ($collections as $collection) {
                    $pivot = $collection->pivot;
                    $productCollectionRelations[] = [
                        'product_name' => $product->name,
                        'collection_name' => $collection->name,
                        'sort_order' => $pivot->sort_order ?? null,
                    ];
                }
                
                // Don't include Stripe IDs unless requested
                if (!$includeStripeIds) {
                    unset($productData['stripe_product_id']);
                    unset($productData['stripe_account_id']);
                    unset($productData['default_price']);
                }
                
                $productsData[] = $productData;
            }
            
            $this->info("Exported {$products->count()} products");

            // Create manifest
            $manifest = [
                'export_date' => now()->toIso8601String(),
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                ],
                'collections' => $collectionsData,
                'products' => $productsData,
                'product_collection_relations' => $productCollectionRelations,
                'include_stripe_ids' => $includeStripeIds,
            ];

            // Save manifest
            file_put_contents($tempDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

            // Create zip file
            if (!$outputPath) {
                $outputPath = storage_path('app/products-export-' . date('Y-m-d-His') . '.zip');
            }

            $this->info("\nCreating zip file...");
            $zip = new ZipArchive();
            if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->error("Failed to create zip file: {$outputPath}");
                return 1;
            }

            // Add manifest
            $zip->addFile($tempDir . '/manifest.json', 'manifest.json');

            // Add media files
            $this->info("Adding media files to zip...");
            $this->addDirectoryToZip($zip, $mediaDir, 'media');

            $zip->close();

            // Clean up temp directory
            File::deleteDirectory($tempDir);

            $this->info("\n✅ Export completed successfully!");
            $this->info("Export file: {$outputPath}");
            $this->info("Collections: " . count($collectionsData));
            $this->info("Products: " . count($productsData));
            $this->info("Product-Collection relations: " . count($productCollectionRelations));

            return 0;
        } catch (\Exception $e) {
            $this->error("Export failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            
            // Clean up temp directory on error
            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            
            return 1;
        }
    }

    protected function copyCollectionImage(Collection $collection, string $mediaDir): ?string
    {
        $imageUrl = $collection->image_url;
        if (!$imageUrl) {
            return null;
        }

        // Check if it's a local storage path
        if (str_contains($imageUrl, '/storage/')) {
            // Extract path from URL
            $parts = explode('/storage/', $imageUrl);
            $relativePath = ltrim($parts[1] ?? '', '/');
            
            $sourcePath = storage_path('app/public/' . $relativePath);
            if (file_exists($sourcePath)) {
                $fileName = basename($relativePath);
                $destPath = $mediaDir . '/collections/' . $fileName;
                $destDir = dirname($destPath);
                if (!File::exists($destDir)) {
                    File::makeDirectory($destDir, 0755, true);
                }
                File::copy($sourcePath, $destPath);
                return 'collections/' . $fileName;
            }
        } elseif (str_starts_with($imageUrl, 'collections/')) {
            // Direct path
            $sourcePath = storage_path('app/public/' . $imageUrl);
            if (file_exists($sourcePath)) {
                $fileName = basename($imageUrl);
                $destPath = $mediaDir . '/collections/' . $fileName;
                $destDir = dirname($destPath);
                if (!File::exists($destDir)) {
                    File::makeDirectory($destDir, 0755, true);
                }
                File::copy($sourcePath, $destPath);
                return 'collections/' . $fileName;
            }
        }

        // If it's an external URL, we'll need to download it during import
        return null;
    }

    protected function copyMediaFile($media, string $mediaDir, string $prefix): ?string
    {
        try {
            $sourcePath = $media->getPath();
            if (!file_exists($sourcePath)) {
                $this->warn("Media file not found: {$sourcePath}");
                return null;
            }

            $fileName = $media->file_name;
            $destPath = $mediaDir . '/products/' . $prefix . '-' . $fileName;
            $destDir = dirname($destPath);
            if (!File::exists($destDir)) {
                File::makeDirectory($destDir, 0755, true);
            }
            File::copy($sourcePath, $destPath);
            
            return 'products/' . $prefix . '-' . $fileName;
        } catch (\Exception $e) {
            $this->warn("Failed to copy media file: {$e->getMessage()}");
            return null;
        }
    }

    protected function copyVariantImage(ProductVariant $variant, string $mediaDir, string $prefix): ?string
    {
        $imageUrl = $variant->image_url;
        if (!$imageUrl) {
            return null;
        }

        // Check if it's a local storage path
        if (str_contains($imageUrl, '/storage/')) {
            $parts = explode('/storage/', $imageUrl);
            $relativePath = ltrim($parts[1] ?? '', '/');
            
            $sourcePath = storage_path('app/public/' . $relativePath);
            if (file_exists($sourcePath)) {
                $fileName = basename($relativePath);
                $destPath = $mediaDir . '/variants/' . $prefix . '-' . $fileName;
                $destDir = dirname($destPath);
                if (!File::exists($destDir)) {
                    File::makeDirectory($destDir, 0755, true);
                }
                File::copy($sourcePath, $destPath);
                return 'variants/' . $prefix . '-' . $fileName;
            }
        } elseif (str_starts_with($imageUrl, 'variants/') || str_starts_with($imageUrl, 'products/')) {
            $sourcePath = storage_path('app/public/' . $imageUrl);
            if (file_exists($sourcePath)) {
                $fileName = basename($imageUrl);
                $destPath = $mediaDir . '/variants/' . $prefix . '-' . $fileName;
                $destDir = dirname($destPath);
                if (!File::exists($destDir)) {
                    File::makeDirectory($destDir, 0755, true);
                }
                File::copy($sourcePath, $destPath);
                return 'variants/' . $prefix . '-' . $fileName;
            }
        }

        return null;
    }

    protected function addDirectoryToZip(ZipArchive $zip, string $dir, string $zipPath): void
    {
        $files = File::allFiles($dir);
        foreach ($files as $file) {
            $relativePath = str_replace($dir . '/', '', $file->getPathname());
            $zip->addFile($file->getPathname(), $zipPath . '/' . $relativePath);
        }
    }
}

