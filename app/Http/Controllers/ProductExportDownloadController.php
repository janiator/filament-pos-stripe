<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductExportDownloadController extends Controller
{
    /**
     * One-time download of a product export zip. Token is stored in cache by the bulk action.
     */
    public function __invoke(Request $request, string $tenant, string $token): StreamedResponse
    {
        $store = \App\Models\Store::where('slug', $tenant)->first();
        if (! $store) {
            abort(404, 'Store not found.');
        }

        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }
        $isSuperAdmin = $user->hasRole('super_admin');
        $hasStoreAccess = $user->stores()->where('stores.id', $store->id)->exists();
        if (! $isSuperAdmin && ! $hasStoreAccess) {
            abort(403, 'You do not have access to this store.');
        }

        $path = Cache::pull('product-export-download:'.$token);

        if (! $path || ! is_string($path) || ! is_file($path)) {
            abort(404, 'Export file not found or link expired.');
        }

        $filename = 'products-export-'.date('Y-m-d-His').'.zip';

        return response()->streamDownload(
            function () use ($path) {
                $stream = fopen($path, 'r');
                if ($stream) {
                    fpassthru($stream);
                    fclose($stream);
                }
                @unlink($path);
            },
            $filename,
            [
                'Content-Type' => 'application/zip',
            ]
        );
    }
}
