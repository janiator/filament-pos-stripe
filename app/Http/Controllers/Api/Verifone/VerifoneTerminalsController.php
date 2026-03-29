<?php

namespace App\Http\Controllers\Api\Verifone;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifoneTerminalsController extends BaseApiController
{
    public function index(Request $request, Store $store): JsonResponse
    {
        $this->authorizeTenant($request, $store);

        $terminals = $store->verifoneTerminals()
            ->orderBy('display_name')
            ->get()
            ->map(fn ($terminal) => [
                'id' => $terminal->id,
                'terminal_identifier' => $terminal->terminal_identifier,
                'display_name' => $terminal->display_name,
                'sale_id' => $terminal->sale_id,
                'operator_id' => $terminal->operator_id,
                'site_entity_id' => $terminal->site_entity_id,
                'is_active' => $terminal->is_active,
                'pos_device_id' => $terminal->pos_device_id,
            ]);

        return response()->json([
            'data' => $terminals,
        ]);
    }
}
