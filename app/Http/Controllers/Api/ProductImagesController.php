<?php

namespace App\Http\Controllers\Api;

use App\Models\ConnectedProduct;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImagesController extends BaseApiController
{
    /**
     * Serve a product image with signed URL validation
     * 
     * This route validates the signed URL signature to ensure the request is authorized
     * and hasn't been tampered with. The signed URL expires after 24 hours.
     */
    public function serve(Request $request, string $productId, string $mediaId): StreamedResponse|JsonResponse
    {
        try {
            // Verify the signed URL first - this provides the security
            // The signature ensures the URL hasn't been tampered with and hasn't expired
            if (!URL::hasValidSignature($request)) {
                return response()->json(['error' => 'Invalid or expired URL'], 403);
            }

            // Find the product
            $product = ConnectedProduct::where('id', $productId)->firstOrFail();

            // Find the media file
            $media = $product->getMedia('images')->firstWhere('id', $mediaId);
            
            if (!$media) {
                return response()->json(['error' => 'Image not found'], 404);
            }

            // Get the file path
            $path = $media->getPath();
            
            if (!file_exists($path)) {
                return response()->json(['error' => 'File not found on disk'], 404);
            }

            // Get the relative path from storage
            $relativePath = str_replace(storage_path('app/public'), '', $path);
            $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

            // Serve the file
            return Storage::disk('public')->response($relativePath, $media->file_name, [
                'Content-Type' => $media->mime_type ?? 'image/jpeg',
                'Content-Disposition' => 'inline; filename="' . $media->file_name . '"',
                'Cache-Control' => 'public, max-age=3600', // Cache for 1 hour
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Product or image not found'], 404);
        } catch (\Throwable $e) {
            \Log::error('Error serving product image', [
                'product_id' => $productId,
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to serve image'], 500);
        }
    }
}

