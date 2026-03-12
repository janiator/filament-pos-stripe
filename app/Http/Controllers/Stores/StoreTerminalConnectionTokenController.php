<?php

namespace App\Http\Controllers\Stores;

use App\Models\PosDevice;
use App\Models\Store;
use App\Models\TerminalLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StoreTerminalConnectionTokenController extends Controller
{
    public function __invoke(Request $request, string $store): JsonResponse
    {
        // Find store by slug
        $storeModel = Store::where('slug', $store)->first();

        if (! $storeModel) {
            return response()->json([
                'message' => 'Store not found.',
            ], 404);
        }

        // TODO: Authorize the caller

        $locationId = $request->input('location_id');
        $posDeviceId = $request->input('pos_device_id');

        $location = null;
        $posDevice = null;

        if ($locationId) {
            $location = TerminalLocation::where('id', $locationId)
                ->where('store_id', $storeModel->id)
                ->first();

            if (! $location) {
                return response()->json([
                    'message' => 'Terminal location not found for this store.',
                ], 404);
            }
        } elseif ($posDeviceId) {
            $posDevice = PosDevice::where('store_id', $storeModel->id)
                ->where('id', $posDeviceId)
                ->with(['terminalLocation', 'lastConnectedTerminalReader'])
                ->first();

            if (! $posDevice) {
                return response()->json([
                    'message' => 'POS device not found for this store.',
                ], 404);
            }

            $location = $posDevice->terminalLocation;
        }

        if (! $location) {
            // Try the store's default terminal location first
            if ($storeModel->default_terminal_location_id) {
                $location = TerminalLocation::where('id', $storeModel->default_terminal_location_id)
                    ->where('store_id', $storeModel->id)
                    ->first();
            }

            // Fall back to auto-detection if no default or default was deleted
            if (empty($location)) {
                $locations = TerminalLocation::where('store_id', $storeModel->id)->get();

                if ($locations->isEmpty()) {
                    return response()->json([
                        'message' => 'This store has no terminal locations configured.',
                    ], 404);
                }

                if ($locations->count() > 1) {
                    return response()->json([
                        'message' => 'Multiple terminal locations exist. Please set a default terminal location or provide a location_id or pos_device_id.',
                    ], 422);
                }

                $location = $locations->first();
            }
        }

        $connectionToken = $storeModel->createConnectionToken([
            'location' => $location->stripe_location_id,
        ], true); // true = connected account

        $payload = [
            'secret' => $connectionToken->secret,
            'location' => $location->stripe_location_id,
            'location_id' => $location->id,
        ];

        if ($posDevice && $posDevice->lastConnectedTerminalReader) {
            $payload['preferred_reader_id'] = $posDevice->lastConnectedTerminalReader->stripe_reader_id;
        }

        return response()->json($payload, 200);
    }
}
