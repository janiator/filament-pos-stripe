<?php

namespace App\Http\Controllers\Stores;

use App\Models\Store;
use App\Models\TerminalLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StoreTerminalConnectionTokenController extends Controller
{
    public function __invoke(Store $store, Request $request): JsonResponse
    {
        // TODO: Authorize the caller

        $locationId = $request->input('location_id');

        if ($locationId) {
            $location = TerminalLocation::where('id', $locationId)
                ->where('store_id', $store->id)
                ->first();

            if (! $location) {
                return response()->json([
                    'message' => 'Terminal location not found for this store.',
                ], 404);
            }
        } else {
            $locations = TerminalLocation::where('store_id', $store->id)->get();

            if ($locations->isEmpty()) {
                return response()->json([
                    'message' => 'This store has no terminal locations configured.',
                ], 404);
            }

            if ($locations->count() > 1) {
                return response()->json([
                    'message' => 'Multiple terminal locations exist. Please provide a location_id.',
                ], 422);
            }

            $location = $locations->first();
        }

        // FIX: pass params as an array, not a string
        $connectionToken = $store->createConnectionToken([
            'location' => $location->stripe_location_id,
        ], true); // true = connected account

        return response()->json($connectionToken, 200);
    }
}
