<?php

namespace App\Actions\ConnectedProducts;

use App\Models\ConnectedProduct;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class UploadProductImagesToStripe
{
    /**
     * Upload product images to Stripe File API and return URLs
     */
    public function __invoke(ConnectedProduct $product): array
    {
        if (! $product->stripe_account_id) {
            return [];
        }

        $store = Store::where('stripe_account_id', $product->stripe_account_id)->first();
        if (! $store || ! $store->hasStripeAccount()) {
            return [];
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (! $secret) {
            return [];
        }

        $stripe = new StripeClient($secret);
        $imageUrls = [];

        try {
            // Get all media files from the images collection
            $mediaFiles = $product->getMedia('images');

            foreach ($mediaFiles as $media) {
                try {
                    // Get the file path
                    $filePath = $media->getPath();
                    
                    if (! file_exists($filePath)) {
                        Log::warning('Product image file not found', [
                            'product_id' => $product->id,
                            'media_id' => $media->id,
                            'path' => $filePath,
                        ]);
                        continue;
                    }

                    // Upload to Stripe File API
                    // Stripe File API requires a file resource handle
                    $fileHandle = fopen($filePath, 'rb');
                    
                    if (!$fileHandle) {
                        Log::warning('Failed to open file for Stripe upload', [
                            'product_id' => $product->id,
                            'media_id' => $media->id,
                            'path' => $filePath,
                        ]);
                        continue;
                    }
                    
                    try {
                        // Upload file to Stripe File API
                        $file = $stripe->files->create([
                            'purpose' => 'product_image',
                            'file' => $fileHandle,
                        ], [
                            'stripe_account' => $product->stripe_account_id,
                        ]);
                        
                        if (!isset($file->id)) {
                            Log::error('Stripe file upload did not return file ID', [
                                'product_id' => $product->id,
                                'media_id' => $media->id,
                                'file_response' => json_encode($file),
                            ]);
                            continue;
                        }
                        
                        // Always create a file link for product images
                        // Stripe products require file link URLs, not file contents URLs
                        // The /contents URL is for downloading, not for product images
                        $fileUrl = null;
                        try {
                            // Create a file link - this gives us a public URL for the file
                            // Don't set expires_at if we want it to never expire (omit the parameter)
                            $linkParams = [
                                'file' => $file->id,
                            ];
                            
                            $link = $stripe->fileLinks->create($linkParams, [
                                'stripe_account' => $product->stripe_account_id,
                            ]);
                            
                            if (!isset($link->url)) {
                                Log::error('File link created but no URL returned', [
                                    'product_id' => $product->id,
                                    'file_id' => $file->id,
                                    'link_id' => $link->id ?? null,
                                    'link_response' => json_encode($link),
                                ]);
                                continue;
                            }
                            
                            $fileUrl = $link->url;
                            
                            // Verify it's a file link URL, not a contents URL
                            if (str_contains($fileUrl, '/contents')) {
                                Log::warning('File link URL contains /contents, may not work for product images', [
                                    'product_id' => $product->id,
                                    'file_id' => $file->id,
                                    'url' => $fileUrl,
                                ]);
                            }
                            
                            Log::info('Created file link for product image', [
                                'product_id' => $product->id,
                                'file_id' => $file->id,
                                'link_id' => $link->id ?? null,
                                'url' => $fileUrl,
                            ]);
                        } catch (Throwable $linkError) {
                            Log::error('Failed to create file link for Stripe file', [
                                'product_id' => $product->id,
                                'file_id' => $file->id,
                                'media_id' => $media->id,
                                'error' => $linkError->getMessage(),
                                'file' => $linkError->getFile(),
                                'line' => $linkError->getLine(),
                                'trace' => $linkError->getTraceAsString(),
                            ]);
                            continue; // Skip this image if we can't create a link
                        }
                    } finally {
                        fclose($fileHandle);
                    }
                    
                    if ($fileUrl) {
                        // Ensure we're not using a /contents URL (these don't work for product images)
                        if (str_contains($fileUrl, '/contents')) {
                            Log::error('File link URL contains /contents, cannot use for product images', [
                                'product_id' => $product->id,
                                'stripe_file_id' => $file->id ?? null,
                                'url' => $fileUrl,
                            ]);
                            continue; // Skip this image
                        }
                        
                        $imageUrls[] = $fileUrl;
                        Log::info('Uploaded product image to Stripe', [
                            'product_id' => $product->id,
                            'stripe_file_id' => $file->id ?? null,
                            'link_url' => $fileUrl,
                        ]);
                    } else {
                        Log::warning('No file URL obtained for uploaded image', [
                            'product_id' => $product->id,
                            'stripe_file_id' => $file->id ?? null,
                            'media_id' => $media->id,
                        ]);
                    }
                } catch (Throwable $e) {
                    Log::error('Failed to upload product image to Stripe', [
                        'product_id' => $product->id,
                        'media_id' => $media->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other images even if one fails
                }
            }
        } catch (Throwable $e) {
            Log::error('Failed to process product images for Stripe upload', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $imageUrls;
    }
}

