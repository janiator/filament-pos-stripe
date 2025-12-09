<?php

namespace App\Http\Controllers\Api;

use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectionImagesController extends BaseApiController
{
    /**
     * Serve a collection image with signed URL validation
     * 
     * This route validates the signed URL signature to ensure the request is authorized
     * and hasn't been tampered with. The signed URL expires after 24 hours.
     */
    public function serve(Request $request, string $collectionId): StreamedResponse|JsonResponse
    {
        try {
            // Verify the signed URL first - this provides the security
            // The signature ensures the URL hasn't been tampered with and hasn't expired
            if (!URL::hasValidSignature($request)) {
                return response()->json(['error' => 'Invalid or expired URL'], 403);
            }

            // Find the collection
            $collection = Collection::findOrFail($collectionId);
            
            if (!$collection->image_url) {
                return response()->json(['error' => 'Collection image not found'], 404);
            }

            // Check if it's a local storage URL
            $imageUrl = $collection->image_url;
            
            // Log the original URL for debugging
            \Log::debug('Collection image URL', [
                'collection_id' => $collection->id,
                'image_url' => $imageUrl,
            ]);
            
            // Check if it's an external URL (doesn't contain /storage/)
            if (!str_contains($imageUrl, '/storage/') && !str_starts_with($imageUrl, '/storage/')) {
                // External URL - redirect to it
                return redirect($imageUrl);
            }

            // Extract the relative path from the URL
            // Handle multiple URL formats:
            // 1. Full URL: https://domain.com/storage/collections/file.jpg
            // 2. Relative URL: /storage/collections/file.jpg  
            // 3. Already relative: collections/file.jpg
            
            $relativePath = $imageUrl;
            
            // Check if it's a temporary file path (shouldn't happen, but handle gracefully)
            if (str_contains($relativePath, '/private/var/tmp/') || str_contains($relativePath, '/tmp/')) {
                \Log::error('Collection image URL points to temporary file', [
                    'collection_id' => $collection->id,
                    'image_url' => $imageUrl,
                ]);
                return response()->json([
                    'error' => 'Invalid image URL (temporary file path). Please re-upload the image.',
                ], 400);
            }
            
            // If it's already a relative path (starts with collections/), use it directly
            if (str_starts_with($relativePath, 'collections/')) {
                // Already in the correct format
                $relativePath = $relativePath;
            }
            // If it contains /storage/, extract the part after it
            elseif (str_contains($relativePath, '/storage/')) {
                $parts = explode('/storage/', $relativePath, 2);
                $relativePath = $parts[1] ?? $relativePath;
            }
            // If it starts with /storage/, remove that prefix
            elseif (str_starts_with($relativePath, '/storage/')) {
                $relativePath = substr($relativePath, 9); // Remove '/storage/'
            }
            
            // Remove leading slashes
            $relativePath = ltrim($relativePath, '/');
            
            // Remove query parameters and fragments if present
            $relativePath = parse_url($relativePath, PHP_URL_PATH) ?? $relativePath;
            
            // Log extracted path for debugging
            \Log::debug('Collection image path extraction', [
                'collection_id' => $collection->id,
                'original_url' => $imageUrl,
                'extracted_path' => $relativePath,
                'storage_exists' => Storage::disk('public')->exists($relativePath),
                'full_path' => storage_path('app/public/' . $relativePath),
                'file_exists' => file_exists(storage_path('app/public/' . $relativePath)),
            ]);
            
            // Check if file exists
            if (!Storage::disk('public')->exists($relativePath)) {
                // Try alternative path extraction methods
                $alternatives = [];
                
                // Try without removing leading slash
                $alt1 = ltrim($imageUrl, '/');
                if (str_contains($alt1, '/storage/')) {
                    $parts = explode('/storage/', $alt1, 2);
                    $alternatives[] = ltrim($parts[1] ?? '', '/');
                }
                
                // Try direct path if it looks like a path already
                if (str_starts_with($imageUrl, 'collections/')) {
                    $alternatives[] = $imageUrl;
                }
                
                \Log::error('Collection image file not found', [
                    'collection_id' => $collection->id,
                    'image_url' => $imageUrl,
                    'extracted_path' => $relativePath,
                    'full_storage_path' => storage_path('app/public/' . $relativePath),
                    'file_exists' => file_exists(storage_path('app/public/' . $relativePath)),
                    'alternative_paths' => $alternatives,
                    'storage_listing' => Storage::disk('public')->files('collections'),
                ]);
                return response()->json([
                    'error' => 'File not found on disk',
                    'debug' => [
                        'image_url' => $imageUrl,
                        'extracted_path' => $relativePath,
                        'full_path' => storage_path('app/public/' . $relativePath),
                    ]
                ], 404);
            }

            // Get file info
            $fileName = basename($relativePath);
            $mimeType = Storage::disk('public')->mimeType($relativePath) ?? 'image/jpeg';

            // Serve the file
            return Storage::disk('public')->response($relativePath, $fileName, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                'Cache-Control' => 'public, max-age=3600', // Cache for 1 hour
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Collection not found'], 404);
        } catch (\Throwable $e) {
            \Log::error('Error serving collection image', [
                'collection_id' => $collectionId ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to serve image'], 500);
        }
    }
}
