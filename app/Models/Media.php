<?php

namespace App\Models;

use App\Jobs\SyncConnectedProductToStripeJob;
use App\Models\ConnectedProduct;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

class Media extends BaseMedia
{
    /**
     * Get the URL to the media file.
     */
    public function getUrl(string $conversionName = ''): string
    {
        // If we're in a web request, use the current request URL
        if (app()->runningInConsole() === false && request()->hasHeader('Host')) {
            $scheme = request()->getScheme();
            $host = request()->getHost();
            $port = request()->getPort();
            $baseUrl = $scheme . '://' . $host . ($port && $port != 80 && $port != 443 ? ':' . $port : '');
            
            // Get the relative path from the media
            $path = $this->getPath($conversionName);
            
            // Convert absolute path to relative path
            if (str_starts_with($path, public_path('storage'))) {
                $relativePath = str_replace(public_path('storage'), '', $path);
                return $baseUrl . '/storage' . $relativePath;
            }
            
            // If it's already a relative path or URL, use it
            if (str_starts_with($path, '/')) {
                return $baseUrl . $path;
            }
        }
        
        // Fallback to parent implementation
        return parent::getUrl($conversionName);
    }
    
    /**
     * Override delete to trigger sync when individual media is deleted
     */
    public function delete(): bool
    {
        $model = $this->model;
        $collectionName = $this->collection_name;
        $isImageCollection = $collectionName === 'images';
        $isConnectedProduct = $model instanceof ConnectedProduct;
        
        $result = parent::delete();
        
        // If an image was deleted from a ConnectedProduct, trigger sync
        if ($result && $isImageCollection && $isConnectedProduct) {
            /** @var ConnectedProduct $product */
            $product = $model;
            
            if ($product->stripe_product_id && $product->stripe_account_id) {
                $product->refresh(); // Refresh to get updated media count
                SyncConnectedProductToStripeJob::dispatch($product);
            }
        }
        
        return $result;
    }
}

