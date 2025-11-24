<?php

namespace App\Http\Controllers\Api;

use App\Models\PosDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosDevicesController extends BaseApiController
{
    /**
     * Get all POS devices for the current store
     */
    public function index(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        $this->authorizeTenant($request, $store);

        $devices = PosDevice::where('store_id', $store->id)
            ->with(['terminalLocations.terminalReaders'])
            ->orderBy('device_name')
            ->get();

        return response()->json([
            'devices' => $devices->map(function ($device) {
                return $this->formatDeviceResponse($device);
            }),
        ]);
    }

    /**
     * Register a new POS device
     */
    public function store(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'device_identifier' => 'required|string|unique:pos_devices,device_identifier',
            'device_name' => 'required|string|max:255',
            'platform' => 'required|string|in:ios,android',
            'device_model' => 'nullable|string|max:255',
            'device_brand' => 'nullable|string|max:255',
            'device_manufacturer' => 'nullable|string|max:255',
            'device_product' => 'nullable|string|max:255',
            'device_hardware' => 'nullable|string|max:255',
            'machine_identifier' => 'nullable|string|max:255',
            'system_name' => 'nullable|string|max:255',
            'system_version' => 'nullable|string|max:255',
            'vendor_identifier' => 'nullable|string|max:255',
            'android_id' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'device_metadata' => 'nullable|array',
        ]);

        $validated['store_id'] = $store->id;
        $validated['device_status'] = 'active';
        $validated['last_seen_at'] = now();

        $device = PosDevice::create($validated);

        return response()->json([
            'message' => 'POS device registered successfully',
            'device' => $this->formatDeviceResponse($device),
        ], 201);
    }

    /**
     * Get a specific POS device
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        $this->authorizeTenant($request, $store);

        $device = PosDevice::where('id', $id)
            ->where('store_id', $store->id)
            ->with(['terminalLocations.terminalReaders'])
            ->firstOrFail();

        return response()->json([
            'device' => $this->formatDeviceResponse($device),
        ]);
    }

    /**
     * Update POS device information
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        $this->authorizeTenant($request, $store);

        $device = PosDevice::where('id', $id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $validated = $request->validate([
            'device_name' => 'sometimes|string|max:255',
            'device_model' => 'nullable|string|max:255',
            'device_brand' => 'nullable|string|max:255',
            'device_manufacturer' => 'nullable|string|max:255',
            'device_product' => 'nullable|string|max:255',
            'device_hardware' => 'nullable|string|max:255',
            'machine_identifier' => 'nullable|string|max:255',
            'system_name' => 'nullable|string|max:255',
            'system_version' => 'nullable|string|max:255',
            'vendor_identifier' => 'nullable|string|max:255',
            'android_id' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'device_status' => 'sometimes|string|in:active,inactive,maintenance,offline',
            'device_metadata' => 'nullable|array',
        ]);

        $device->update($validated);

        return response()->json([
            'message' => 'POS device updated successfully',
            'device' => $this->formatDeviceResponse($device),
        ]);
    }

    /**
     * Update device heartbeat (last_seen_at and optional status/metadata)
     */
    public function heartbeat(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        $this->authorizeTenant($request, $store);

        $device = PosDevice::where('id', $id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $validated = $request->validate([
            'device_status' => 'sometimes|string|in:active,inactive,maintenance,offline',
            'device_metadata' => 'nullable|array',
        ]);

        $validated['last_seen_at'] = now();

        $device->update($validated);

        return response()->json([
            'message' => 'Device heartbeat updated',
            'device' => $this->formatDeviceResponse($device),
        ]);
    }

    /**
     * Format device response with all information
     */
    protected function formatDeviceResponse(PosDevice $device): array
    {
        return [
            'id' => $device->id,
            'device_identifier' => $device->device_identifier,
            'device_name' => $device->device_name,
            'platform' => $device->platform,
            'device_info' => [
                'model' => $device->device_model,
                'brand' => $device->device_brand,
                'manufacturer' => $device->device_manufacturer,
                'product' => $device->device_product,
                'hardware' => $device->device_hardware,
                'machine_identifier' => $device->machine_identifier,
            ],
            'system_info' => [
                'name' => $device->system_name,
                'version' => $device->system_version,
            ],
            'identifiers' => [
                'vendor_identifier' => $device->vendor_identifier,
                'android_id' => $device->android_id,
                'serial_number' => $device->serial_number,
            ],
            'device_status' => $device->device_status,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'device_metadata' => $device->device_metadata,
            'terminal_locations_count' => $device->terminalLocations->count(),
            'terminal_locations' => $device->terminalLocations->map(function ($location) {
                return [
                    'id' => $location->id,
                    'display_name' => $location->display_name,
                    'stripe_location_id' => $location->stripe_location_id,
                    'readers_count' => $location->terminalReaders->count(),
                ];
            }),
            'created_at' => $device->created_at->toIso8601String(),
            'updated_at' => $device->updated_at->toIso8601String(),
        ];
    }
}
