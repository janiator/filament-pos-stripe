<?php

namespace App\Actions\ConnectedProducts;

use App\Models\ConnectedProduct;
use App\Models\Store;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class UpdateConnectedProductToStripe
{
    public function __invoke(ConnectedProduct $product): void
    {
        if (! $product->stripe_product_id || ! $product->stripe_account_id) {
            return;
        }

        $store = Store::where('stripe_account_id', $product->stripe_account_id)->first();
        if (! $store || ! $store->hasStripeAccount()) {
            return;
        }

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (! $secret) {
            return;
        }

        $stripe = new StripeClient($secret);

        try {
            $updateData = [];

            // Add all syncable fields (listener already checked for changes)
            if ($product->name !== null) {
                $updateData['name'] = $product->name;
            }

            if ($product->description !== null) {
                $updateData['description'] = $product->description;
            }

            $updateData['active'] = $product->active ?? true;

            // Handle images - check if media library has images, otherwise use stored URLs
            $imageUrls = [];
            
            // Check if product has media library images
            if ($product->hasMedia('images')) {
                // Upload media library images to Stripe File API
                $uploadAction = new UploadProductImagesToStripe();
                $imageUrls = $uploadAction($product);
                
                // Update the images field with Stripe URLs
                if (!empty($imageUrls)) {
                    $product->images = $imageUrls;
                    $product->saveQuietly(); // Save without triggering events
                } else {
                    // If upload returned empty (shouldn't happen, but handle it)
                    $product->images = [];
                    $product->saveQuietly();
                }
            } else {
                // No media library images - clear the images field and Stripe images
                $imageUrls = [];
                if ($product->images !== null) {
                    // Clear stored images since media was removed
                    $product->images = [];
                    $product->saveQuietly(); // Save without triggering events
                }
            }

            // Always set images array (empty array removes images from Stripe)
            $updateData['images'] = $imageUrls;

            if ($product->product_meta !== null && is_array($product->product_meta)) {
                // Ensure all metadata values are strings (Stripe requirement)
                // Filter out any internal Stripe keys and ensure values only contains string values
                $metadata = [];
                foreach ($product->product_meta as $key => $value) {
                    // Skip non-string keys or keys with null bytes or internal Stripe options
                    if (!is_string($key) || 
                        str_contains($key, "\0") || 
                        str_contains($key, '*') || 
                        str_contains($key, '_opts') ||
                        str_starts_with($key, '_')) {
                        Log::warning('Skipping invalid metadata key', [
                            'key' => bin2hex($key), // Log hex representation to see null bytes
                            'product_id' => $product->id,
                        ]);
                        continue;
                    }
                    
                    // Convert all values to strings
                    if (is_string($value)) {
                        $metadata[$key] = $value;
                    } elseif (is_scalar($value)) {
                        $metadata[$key] = (string) $value;
                    } elseif (is_null($value)) {
                        // Skip null values
                        continue;
                    } else {
                        // For arrays/objects, encode as JSON
                        $metadata[$key] = json_encode($value);
                    }
                }
                
                // Only include metadata if it's not empty
                if (!empty($metadata)) {
                    $updateData['metadata'] = $metadata;
                }
            }

            if ($product->url !== null) {
                $updateData['url'] = $product->url;
            }

            if ($product->package_dimensions !== null) {
                $updateData['package_dimensions'] = $product->package_dimensions;
            }

            if ($product->shippable !== null) {
                $updateData['shippable'] = $product->shippable;
            }

            if ($product->statement_descriptor !== null) {
                $updateData['statement_descriptor'] = $product->statement_descriptor;
            }

            if ($product->tax_code !== null) {
                $updateData['tax_code'] = $product->tax_code;
            }

            if ($product->unit_label !== null) {
                $updateData['unit_label'] = $product->unit_label;
            }

            if ($product->default_price !== null) {
                $updateData['default_price'] = $product->default_price;
            }

            if (! empty($updateData)) {
                // Clean updateData to ensure no invalid data is passed
                $cleanUpdateData = array_filter($updateData, function ($value, $key) {
                    // Filter out any keys that might cause issues
                    if (!is_string($key) || str_contains($key, "\0") || str_contains($key, '*')) {
                        return false;
                    }
                    return true;
                }, ARRAY_FILTER_USE_BOTH);
                
                $stripe->products->update(
                    $product->stripe_product_id,
                    $cleanUpdateData,
                    ['stripe_account' => $product->stripe_account_id]
                );
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}

