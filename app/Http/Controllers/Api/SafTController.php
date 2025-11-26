<?php

namespace App\Http\Controllers\Api;

use App\Actions\SafT\GenerateSafTCashRegister;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SafTController extends BaseApiController
{
    /**
     * Generate SAF-T Cash Register file
     */
    public function generate(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        try {
            $generator = new GenerateSafTCashRegister();
            $xmlContent = $generator(
                $store,
                $validated['from_date'],
                $validated['to_date']
            );

            // Generate filename
            $filename = sprintf(
                'SAF-T_%s_%s_%s.xml',
                $store->slug,
                $validated['from_date'],
                $validated['to_date']
            );

            // Store file temporarily
            $path = 'saf-t/' . $filename;
            Storage::put($path, $xmlContent);

            // Return download URL or file content
            return response()->json([
                'message' => 'SAF-T file generated successfully',
                'filename' => $filename,
                'download_url' => url("/api/saf-t/download/{$filename}"),
                'size' => strlen($xmlContent),
                'from_date' => $validated['from_date'],
                'to_date' => $validated['to_date'],
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('Failed to generate SAF-T file', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to generate SAF-T file',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Download SAF-T file
     */
    public function download(Request $request, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            abort(404, 'Store not found');
        }

        $this->authorizeTenant($request, $store);

        // Verify filename belongs to this store
        if (!str_starts_with($filename, "SAF-T_{$store->slug}_")) {
            abort(403, 'Unauthorized access to this file');
        }

        $path = 'saf-t/' . $filename;

        if (!Storage::exists($path)) {
            abort(404, 'File not found');
        }

        return Storage::download($path, $filename, [
            'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Get SAF-T file content directly (for API consumption)
     */
    public function content(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        try {
            $generator = new GenerateSafTCashRegister();
            $xmlContent = $generator(
                $store,
                $validated['from_date'],
                $validated['to_date']
            );

            return response($xmlContent, 200, [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => sprintf(
                    'attachment; filename="SAF-T_%s_%s_%s.xml"',
                    $store->slug,
                    $validated['from_date'],
                    $validated['to_date']
                ),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to generate SAF-T file', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to generate SAF-T file',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
