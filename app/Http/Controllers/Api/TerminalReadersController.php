<?php

namespace App\Http\Controllers\Api;

use App\Models\TerminalReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalReadersController extends BaseApiController
{
    /**
     * Get all terminal readers for the current store
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

        $readers = TerminalReader::where('store_id', $store->id)
            ->with(['terminalLocation'])
            ->orderBy('label')
            ->get();

        return response()->json([
            'readers' => $readers->map(function ($reader) {
                return [
                    'id' => $reader->id,
                    'stripe_reader_id' => $reader->stripe_reader_id,
                    'label' => $reader->label,
                    'tap_to_pay' => $reader->tap_to_pay,
                    'device_type' => $reader->device_type,
                    'status' => $reader->status,
                    'location' => $reader->terminalLocation ? [
                        'id' => $reader->terminalLocation->id,
                        'display_name' => $reader->terminalLocation->display_name,
                        'stripe_location_id' => $reader->terminalLocation->stripe_location_id,
                    ] : null,
                    'created_at' => $reader->created_at,
                    'updated_at' => $reader->updated_at,
                ];
            }),
        ]);
    }
}
