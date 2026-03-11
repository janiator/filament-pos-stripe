<?php

namespace App\Http\Controllers;

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\PosDevice;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BookingSeatmapController extends Controller
{
    public function show(Request $request): RedirectResponse|JsonResponse|Response
    {
        $tenant = trim((string) $request->query('tenant', ''));
        if ($tenant === '') {
            return $this->errorResponse($request, 'Tenant is required.', 422);
        }

        $store = Store::query()
            ->where('slug', $tenant)
            ->orWhere('stripe_account_id', $tenant)
            ->first();
        if (! $store) {
            return $this->errorResponse($request, 'Store not found.', 404);
        }

        if (! Addon::storeHasActiveAddon($store->id, AddonType::MeranoBooking)) {
            return $this->errorResponse($request, 'Merano booking is not enabled for this store.', 503);
        }

        if (! filled($store->merano_base_url) || ! filled($store->merano_pos_api_token)) {
            return $this->errorResponse($request, 'Merano is not configured for this store.', 503);
        }

        $posDeviceId = $request->query('posDeviceId', $request->query('pos_device_id'));
        if (filled($posDeviceId)) {
            if (! ctype_digit((string) $posDeviceId)) {
                return $this->errorResponse($request, 'posDeviceId must be a valid integer.', 422);
            }

            $device = PosDevice::query()
                ->where('store_id', $store->id)
                ->find((int) $posDeviceId);

            if (! $device) {
                return $this->errorResponse($request, 'POS device not found for this store.', 404);
            }

            if (! $device->booking_enabled) {
                return $this->errorResponse($request, 'Booking is not enabled for this device.', 403);
            }
        }

        $targetBaseUrl = rtrim((string) $store->merano_base_url, '/');
        $parsedBasePath = trim((string) parse_url($targetBaseUrl, PHP_URL_PATH), '/');
        $hasSeatmapPath = str_contains($parsedBasePath, 'booking/seatmap');
        $targetUrl = $hasSeatmapPath ? $targetBaseUrl : $targetBaseUrl.'/booking/seatmap';
        $query = $request->query();
        if (! isset($query['posToken']) || trim((string) $query['posToken']) === '') {
            $query['posToken'] = (string) $store->merano_pos_api_token;
        }
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($queryString !== '' ? "{$targetUrl}?{$queryString}" : $targetUrl);
    }

    private function errorResponse(Request $request, string $message, int $status): JsonResponse|Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return response($message, $status)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
