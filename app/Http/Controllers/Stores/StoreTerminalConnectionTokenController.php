<?php

namespace App\Http\Controllers\Stores;

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
        
        if (!$storeModel) {
            return response()->json([
                'message' => 'Store not found.',
            ], 404);
        }
        
        // TODO: Authorize the caller

        $locationId = $request->input('location_id');

        if ($locationId) {
            $location = TerminalLocation::where('id', $locationId)
                ->where('store_id', $storeModel->id)
                ->first();

            if (! $location) {
                return response()->json([
                    'message' => 'Terminal location not found for this store.',
                ], 404);
            }
        } else {
            $locations = TerminalLocation::where('store_id', $storeModel->id)->get();

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

        $connectionToken = $storeModel->createConnectionToken([
            'location' => $location->stripe_location_id,
        ], true); // true = connected account

        // Return secret and location so the client can update app state (e.g. after token refresh)
        return response()->json([
            'secret' => $connectionToken->secret,
            'location' => $location->stripe_location_id,
        ], 200);
    }
}
