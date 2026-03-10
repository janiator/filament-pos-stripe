<?php

namespace App\Http\Controllers\Api;

use App\Enums\AddonType;
use App\Http\Requests\Api\CheckMeranoAvailabilityRequest;
use App\Http\Requests\Api\ConfirmMeranoBookingPaymentRequest;
use App\Http\Requests\Api\CreateMeranoBookingRequest;
use App\Http\Requests\Api\ReleaseMeranoBookingRequest;
use App\Models\Addon;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Http;

class MeranoProxyController extends BaseApiController
{
    public function events(Request $request): JsonResponse|HttpResponse
    {
        $store = $this->getAuthorizedStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        if ($response = $this->ensureMeranoEnabledForStore($store)) {
            return $response;
        }

        if ($response = $this->ensureDeviceCanBook($request, $store)) {
            return $response;
        }

        return $this->forwardRequest($store, 'get', '/api/pos/v1/events');
    }

    public function availability(CheckMeranoAvailabilityRequest $request, string $event): JsonResponse|HttpResponse
    {
        $store = $this->getAuthorizedStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        if ($response = $this->ensureMeranoEnabledForStore($store)) {
            return $response;
        }

        if ($response = $this->ensureDeviceCanBook($request, $store)) {
            return $response;
        }

        $payload = $request->safe()->except(['pos_device_id']);

        return $this->forwardRequest($store, 'post', "/api/pos/v1/events/{$event}/availability", $payload);
    }

    public function createBooking(CreateMeranoBookingRequest $request): JsonResponse|HttpResponse
    {
        $store = $this->getAuthorizedStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        if ($response = $this->ensureMeranoEnabledForStore($store)) {
            return $response;
        }

        if ($response = $this->ensureDeviceCanBook($request, $store)) {
            return $response;
        }

        $payload = $request->safe()->except(['pos_device_id']);

        return $this->forwardRequest($store, 'post', '/api/pos/v1/bookings', $payload);
    }

    public function release(ReleaseMeranoBookingRequest $request, string $booking): JsonResponse|HttpResponse
    {
        $store = $this->getAuthorizedStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        if ($response = $this->ensureMeranoEnabledForStore($store)) {
            return $response;
        }

        if ($response = $this->ensureDeviceCanBook($request, $store)) {
            return $response;
        }

        return $this->forwardRequest($store, 'post', "/api/pos/v1/bookings/{$booking}/release");
    }

    public function confirmPosPayment(ConfirmMeranoBookingPaymentRequest $request, string $booking): JsonResponse|HttpResponse
    {
        $store = $this->getAuthorizedStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        if ($response = $this->ensureMeranoEnabledForStore($store)) {
            return $response;
        }

        if ($response = $this->ensureDeviceCanBook($request, $store)) {
            return $response;
        }

        $payload = $request->safe()->except(['pos_device_id']);

        return $this->forwardRequest($store, 'post', "/api/pos/v1/bookings/{$booking}/confirm-pos-payment", $payload);
    }

    private function getAuthorizedStore(Request $request): Store|JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (! $store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        return $store;
    }

    private function ensureMeranoEnabledForStore(Store $store): ?JsonResponse
    {
        if (! Addon::storeHasActiveAddon($store->id, AddonType::MeranoBooking)) {
            return response()->json([
                'message' => 'Merano booking is not enabled for this store.',
            ], 503);
        }

        if (! $store->merano_base_url || ! $store->merano_pos_api_token) {
            return response()->json([
                'message' => 'Merano is not configured for this store.',
            ], 503);
        }

        return null;
    }

    private function ensureDeviceCanBook(Request $request, Store $store): ?JsonResponse
    {
        $device = $this->resolveDeviceFromRequest($request, $store);

        if ($device instanceof JsonResponse) {
            return $device;
        }

        if (! $device) {
            return null;
        }

        if (! $device->booking_enabled) {
            return response()->json([
                'message' => 'Booking is not enabled for this device.',
            ], 403);
        }

        return null;
    }

    private function resolveDeviceFromRequest(Request $request, Store $store): PosDevice|JsonResponse|null
    {
        $posDeviceId = $request->input('pos_device_id', $request->query('pos_device_id'));

        if ($posDeviceId) {
            $device = PosDevice::query()
                ->where('store_id', $store->id)
                ->find($posDeviceId);

            if (! $device) {
                return response()->json([
                    'message' => 'POS device not found for this store.',
                ], 422);
            }

            return $device;
        }

        $posSessionId = $request->input('pos_session_id', $request->query('pos_session_id'));

        if (! $posSessionId) {
            return null;
        }

        $session = PosSession::query()
            ->where('store_id', $store->id)
            ->with('posDevice')
            ->find($posSessionId);

        if (! $session) {
            return response()->json([
                'message' => 'POS session not found for this store.',
            ], 422);
        }

        if (! $session->posDevice) {
            return response()->json([
                'message' => 'No POS device is attached to the provided POS session.',
            ], 422);
        }

        return $session->posDevice;
    }

    private function forwardRequest(Store $store, string $method, string $path, array $payload = []): JsonResponse|HttpResponse
    {
        $client = Http::baseUrl(rtrim($store->merano_base_url, '/'))
            ->acceptJson()
            ->withToken($store->merano_pos_api_token)
            ->withHeaders([
                'X-POS-API-Token' => $store->merano_pos_api_token,
            ])
            ->timeout(15);

        try {
            $response = match ($method) {
                'get' => $client->get($path, $payload),
                'post' => $client->post($path, $payload),
                default => throw new \InvalidArgumentException("Unsupported method [{$method}]"),
            };
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'Unable to reach Merano.',
                'error' => $exception->getMessage(),
            ], 502);
        }

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type', 'application/json'));
    }
}
