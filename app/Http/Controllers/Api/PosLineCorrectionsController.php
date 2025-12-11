<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\PosLineCorrection;
use App\Models\PosSession;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PosLineCorrectionsController extends BaseApiController
{
    /**
     * List line corrections for a POS session
     */
    public function index(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }
        
        $this->authorizeTenant($request, $store);
        
        $validated = $request->validate([
            'pos_session_id' => 'required|integer|exists:pos_sessions,id',
        ]);
        
        $session = PosSession::where('id', $validated['pos_session_id'])
            ->where('store_id', $store->id)
            ->firstOrFail();
        
        $corrections = PosLineCorrection::where('pos_session_id', $session->id)
            ->with(['user'])
            ->orderBy('occurred_at', 'desc')
            ->get();
        
        return response()->json([
            'data' => $corrections->map(function ($correction) {
                return [
                    'id' => $correction->id,
                    'correction_type' => $correction->correction_type,
                    'quantity_reduction' => $correction->quantity_reduction,
                    'amount_reduction' => $correction->amount_reduction,
                    'reason' => $correction->reason,
                    'original_item_data' => $correction->original_item_data,
                    'corrected_item_data' => $correction->corrected_item_data,
                    'user' => $correction->user ? [
                        'id' => $correction->user->id,
                        'name' => $correction->user->name,
                    ] : null,
                    'occurred_at' => $correction->occurred_at->toISOString(),
                    'created_at' => $correction->created_at->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Create a new line correction
     * Only reductions count as line corrections (per FAQ requirement)
     */
    public function store(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }
        
        $this->authorizeTenant($request, $store);
        
        $validated = $request->validate([
            'pos_session_id' => 'required|integer|exists:pos_sessions,id',
            'correction_type' => 'required|string|max:255',
            'quantity_reduction' => 'required|integer|min:0', // Only reductions count
            'amount_reduction' => 'required|integer|min:0', // Only reductions count
            'reason' => 'nullable|string|max:1000',
            'original_item_data' => 'nullable|array',
            'corrected_item_data' => 'nullable|array',
        ]);
        
        $session = PosSession::where('id', $validated['pos_session_id'])
            ->where('store_id', $store->id)
            ->where('status', 'open') // Only allow corrections for open sessions
            ->firstOrFail();
        
        $correction = PosLineCorrection::create([
            'store_id' => $store->id,
            'pos_session_id' => $session->id,
            'user_id' => $request->user()->id,
            'correction_type' => $validated['correction_type'],
            'quantity_reduction' => $validated['quantity_reduction'],
            'amount_reduction' => $validated['amount_reduction'],
            'reason' => $validated['reason'] ?? null,
            'original_item_data' => $validated['original_item_data'] ?? null,
            'corrected_item_data' => $validated['corrected_item_data'] ?? null,
            'occurred_at' => now(),
        ]);
        
        return response()->json([
            'message' => 'Line correction created successfully',
            'data' => [
                'id' => $correction->id,
                'correction_type' => $correction->correction_type,
                'quantity_reduction' => $correction->quantity_reduction,
                'amount_reduction' => $correction->amount_reduction,
                'reason' => $correction->reason,
                'occurred_at' => $correction->occurred_at->toISOString(),
            ],
        ], 201);
    }

    /**
     * Get a specific line correction
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }
        
        $this->authorizeTenant($request, $store);
        
        $correction = PosLineCorrection::where('id', $id)
            ->where('store_id', $store->id)
            ->with(['user', 'posSession'])
            ->firstOrFail();
        
        return response()->json([
            'data' => [
                'id' => $correction->id,
                'correction_type' => $correction->correction_type,
                'quantity_reduction' => $correction->quantity_reduction,
                'amount_reduction' => $correction->amount_reduction,
                'reason' => $correction->reason,
                'original_item_data' => $correction->original_item_data,
                'corrected_item_data' => $correction->corrected_item_data,
                'user' => $correction->user ? [
                    'id' => $correction->user->id,
                    'name' => $correction->user->name,
                ] : null,
                'pos_session' => [
                    'id' => $correction->posSession->id,
                    'session_number' => $correction->posSession->session_number,
                ],
                'occurred_at' => $correction->occurred_at->toISOString(),
                'created_at' => $correction->created_at->toISOString(),
            ],
        ]);
    }
}
