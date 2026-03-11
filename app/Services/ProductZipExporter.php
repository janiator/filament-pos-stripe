<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\ConnectedProduct;
use App\Models\ProductVariant;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ProductZipExporter
{
    /**
     * Export selected products (and their collections, variants, media) to a zip file
     * compatible with the import zip action / products:import command.
     *
     * @param  BaseCollection<int, ConnectedProduct>  $products  Products to export (will be loaded with relations if not already)
     * @return string Full path to the created zip file
     */
    public function export(BaseCollection $products, bool $includeStripeIds = false): string
    {
        $products = $products->isEmpty() ? $products : ConnectedProduct::query()
            ->with(['vendor', 'collections', 'variants'])
            ->whereIn('id', $products->pluck('id'))
            ->get();

        if ($products->isEmpty()) {
            throw new \InvalidArgumentException('No products to export.');
        }

        $store = $products->first()->store;
        if (! $store) {
            throw new \InvalidArgumentException('Products must belong to a store.');
        }

        $collectionIds = $products->pluck('collections')->flatten()->pluck('id')->unique()->filter()->values();
        $collections = $collectionIds->isEmpty()
            ? collect()
            : Collection::whereIn('id', $collectionIds)->get();

        File::ensureDirectoryExists(storage_path('app/temp'));
        $tempDir = storage_path('app/temp/export-bulk-'.uniqid('', true));
        File::ensureDirectoryExists($tempDir);
        $mediaDir = $tempDir.'/media';
        File::ensureDirectoryExists($mediaDir);

        try {
            $collectionsData = $this->buildCollectionsData($collections, $mediaDir, $includeStripeIds);
            [$productsData, $productCollectionRelations] = $this->buildProductsData($products, $mediaDir, $includeStripeIds);

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

            file_put_contents($tempDir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

            File::ensureDirectoryExists(storage_path('app/exports'));
            $zipPath = storage_path('app/exports/products-export-'.date('Y-m-d-His').'-'.uniqid('', true).'.zip');

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException("Failed to create zip file: {$zipPath}");
            }
            $zip->addFile($tempDir.'/manifest.json', 'manifest.json');
            $this->addDirectoryToZip($zip, $mediaDir, 'media');
            $zip->close();

            File::deleteDirectory($tempDir);

            return $zipPath;
        } catch (\Throwable $e) {
            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            throw $e;
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Collection>  $collections
     * @return array<int, array<string, mixed>>
     */
    protected function buildCollectionsData(BaseCollection $collections, string $mediaDir, bool $includeStripeIds): array
    {
        $data = [];
        foreach ($collections as $collection) {
            $row = $collection->toArray();
            if ($collection->image_url) {
                $imagePath = $this->copyCollectionImage($collection, $mediaDir);
                if ($imagePath) {
                    $row['image_path'] = $imagePath;
                }
            }
            if (! $includeStripeIds) {
                unset($row['stripe_account_id']);
            }
            $data[] = $row;
        }

        return $data;
    }

    /**
     * @param  BaseCollection<int, ConnectedProduct>  $products
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    protected function buildProductsData(BaseCollection $products, string $mediaDir, bool $includeStripeIds): array
    {
        $productsData = [];
        $productCollectionRelations = [];

        foreach ($products as $product) {
            $productData = $product->toArray();

            if ($product->vendor_id && $product->relationLoaded('vendor') && $product->vendor) {
                $productData['vendor'] = [
                    'id' => $product->vendor->id,
                    'name' => $product->vendor->name,
                    'description' => $product->vendor->description,
                    'contact_email' => $product->vendor->contact_email,
                    'contact_phone' => $product->vendor->contact_phone,
                    'active' => $product->vendor->active,
                    'metadata' => $product->vendor->metadata ?? [],
                ];
                $productData['vendor_name'] = $product->vendor->name;
            }

            $productImages = [];
            foreach ($product->getMedia('images') as $media) {
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

            $variantsData = [];
            foreach ($product->variants as $variant) {
                $variantData = $variant->toArray();
                if ($variant->image_url) {
                    $variantImagePath = $this->copyVariantImage($variant, $mediaDir, "variant-{$variant->id}");
                    if ($variantImagePath) {
                        $variantData['image_path'] = $variantImagePath;
                    }
                }
                if (! $includeStripeIds) {
                    unset($variantData['stripe_product_id'], $variantData['stripe_price_id'], $variantData['stripe_account_id']);
                }
                $variantsData[] = $variantData;
            }
            $productData['variants'] = $variantsData;

            foreach ($product->collections as $collection) {
                $pivot = $collection->pivot;
                $productCollectionRelations[] = [
                    'product_name' => $product->name,
                    'collection_name' => $collection->name,
                    'sort_order' => $pivot->sort_order ?? null,
                ];
            }

            if (! $includeStripeIds) {
                unset($productData['stripe_product_id'], $productData['stripe_account_id'], $productData['default_price']);
            }

            $productsData[] = $productData;
        }

        return [$productsData, $productCollectionRelations];
    }

    protected function copyCollectionImage(Collection $collection, string $mediaDir): ?string
    {
        $imageUrl = $collection->image_url;
        if (! $imageUrl) {
            return null;
        }
        if (str_contains($imageUrl, '/storage/')) {
            $parts = explode('/storage/', $imageUrl);
            $relativePath = ltrim($parts[1] ?? '', '/');
            $sourcePath = storage_path('app/public/'.$relativePath);
            if (file_exists($sourcePath)) {
                $fileName = basename($relativePath);
                $destPath = $mediaDir.'/collections/'.$fileName;
                File::ensureDirectoryExists(dirname($destPath), 0755);
                File::copy($sourcePath, $destPath);

                return 'collections/'.$fileName;
            }
        }
        if (str_starts_with($imageUrl, 'collections/')) {
            $sourcePath = storage_path('app/public/'.$imageUrl);
            if (file_exists($sourcePath)) {
                $fileName = basename($imageUrl);
                $destPath = $mediaDir.'/collections/'.$fileName;
                File::ensureDirectoryExists(dirname($destPath), 0755);
                File::copy($sourcePath, $destPath);

                return 'collections/'.$fileName;
            }
        }

        return null;
    }

    protected function copyMediaFile($media, string $mediaDir, string $prefix): ?string
    {
        try {
            $sourcePath = $media->getPath();
            if (! file_exists($sourcePath)) {
                return null;
            }
            $fileName = $media->file_name;
            $destPath = $mediaDir.'/products/'.$prefix.'-'.$fileName;
            File::ensureDirectoryExists(dirname($destPath), 0755);
            File::copy($sourcePath, $destPath);

            return 'products/'.$prefix.'-'.$fileName;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function copyVariantImage(ProductVariant $variant, string $mediaDir, string $prefix): ?string
    {
        $imageUrl = $variant->image_url;
        if (! $imageUrl) {
            return null;
        }
        if (str_contains($imageUrl, '/storage/')) {
            $parts = explode('/storage/', $imageUrl);
            $relativePath = ltrim($parts[1] ?? '', '/');
            $sourcePath = storage_path('app/public/'.$relativePath);
            if (file_exists($sourcePath)) {
                $fileName = basename($relativePath);
                $destPath = $mediaDir.'/variants/'.$prefix.'-'.$fileName;
                File::ensureDirectoryExists(dirname($destPath), 0755);
                File::copy($sourcePath, $destPath);

                return 'variants/'.$prefix.'-'.$fileName;
            }
        }
        if (str_starts_with($imageUrl, 'variants/') || str_starts_with($imageUrl, 'products/')) {
            $sourcePath = storage_path('app/public/'.$imageUrl);
            if (file_exists($sourcePath)) {
                $fileName = basename($imageUrl);
                $destPath = $mediaDir.'/variants/'.$prefix.'-'.$fileName;
                File::ensureDirectoryExists(dirname($destPath), 0755);
                File::copy($sourcePath, $destPath);

                return 'variants/'.$prefix.'-'.$fileName;
            }
        }

        return null;
    }

    protected function addDirectoryToZip(ZipArchive $zip, string $dir, string $zipPath): void
    {
        if (! File::exists($dir)) {
            return;
        }
        $files = File::allFiles($dir);
        foreach ($files as $file) {
            $relativePath = str_replace($dir.\DIRECTORY_SEPARATOR, '', $file->getPathname());
            $zip->addFile($file->getPathname(), $zipPath.'/'.str_replace(\DIRECTORY_SEPARATOR, '/', $relativePath));
        }
    }
}
