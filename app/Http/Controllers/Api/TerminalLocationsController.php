<?php

namespace App\Http\Controllers\Api;

use App\Models\PosDevice;
use App\Models\TerminalLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalLocationsController extends BaseApiController
{
    /**
     * Get all terminal locations for the current store.
     * Optional query: device_identifier — if provided and device has a last-connected terminal,
     * response includes last_connected for auto-reconnect.
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

        $locations = TerminalLocation::where('store_id', $store->id)
            ->with(['terminalReaders', 'posDevice'])
            ->orderBy('display_name')
            ->get();

        $payload = [
            'locations' => $locations->map(function ($location) {
                return [
                    'id' => $location->id,
                    'stripe_location_id' => $location->stripe_location_id,
                    'display_name' => $location->display_name,
                    'pos_device_id' => $location->pos_device_id,
                    'pos_device' => $location->posDevice ? [
                        'id' => $location->posDevice->id,
                        'device_name' => $location->posDevice->device_name,
                        'device_identifier' => $location->posDevice->device_identifier,
                    ] : null,
                    'address' => [
                        'line1' => $location->line1,
                        'line2' => $location->line2,
                        'city' => $location->city,
                        'state' => $location->state,
                        'postal_code' => $location->postal_code,
                        'country' => $location->country,
                    ],
                    'readers_count' => $location->terminalReaders->count(),
                    'readers' => $location->terminalReaders->map(function ($reader) {
                        return [
                            'id' => $reader->id,
                            'label' => $reader->label,
                            'stripe_reader_id' => $reader->stripe_reader_id,
                            'status' => $reader->status,
                        ];
                    }),
                    'created_at' => $this->formatDateTimeOslo($location->created_at),
                    'updated_at' => $this->formatDateTimeOslo($location->updated_at),
                ];
            }),
        ];

        $deviceIdentifier = $request->query('device_identifier');
        if ($deviceIdentifier !== null && $deviceIdentifier !== '') {
            $device = PosDevice::where('store_id', $store->id)
                ->where('device_identifier', $deviceIdentifier)
                ->with(['lastConnectedTerminalLocation', 'lastConnectedTerminalReader'])
                ->first();
            if ($device) {
                $loc = $device->lastConnectedTerminalLocation;
                $reader = $device->lastConnectedTerminalReader;
                if ($loc || $reader) {
                    $payload['last_connected'] = [
                        'location_id' => $loc?->id,
                        'stripe_location_id' => $loc?->stripe_location_id,
                        'location_display_name' => $loc?->display_name,
                        'reader_id' => $reader?->id,
                        'stripe_reader_id' => $reader?->stripe_reader_id,
                        'reader_label' => $reader?->label,
                    ];
                }
            }
        }

        return response()->json($payload);
    }
}
