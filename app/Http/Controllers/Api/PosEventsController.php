<?php

namespace App\Http\Controllers\Api;

use App\Models\PosEvent;
use App\Models\PosSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosEventsController extends BaseApiController
{
    /**
     * List events for the current store
     */
    public function index(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $query = PosEvent::where('store_id', $store->id)
            ->with(['posDevice', 'posSession', 'user', 'relatedCharge']);

        // Filter by event code
        if ($request->has('event_code')) {
            $query->where('event_code', $request->get('event_code'));
        }

        // Filter by event type
        if ($request->has('event_type')) {
            $query->where('event_type', $request->get('event_type'));
        }

        // Filter by session
        if ($request->has('pos_session_id')) {
            $query->where('pos_session_id', $request->get('pos_session_id'));
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('occurred_at', '>=', $request->get('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('occurred_at', '<=', $request->get('to_date'));
        }

        $events = $query->orderBy('occurred_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'events' => $events->getCollection()->map(function ($event) {
                return $this->formatEventResponse($event);
            }),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * Create a new event
     */
    public function store(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'pos_device_id' => 'nullable|exists:pos_devices,id',
            'pos_session_id' => 'nullable|exists:pos_sessions,id',
            'event_code' => 'required|string',
            'event_type' => 'required|string|in:application,user,drawer,report,transaction,payment,session,other',
            'description' => 'nullable|string|max:1000',
            'related_charge_id' => 'nullable|exists:connected_charges,id',
            'event_data' => 'nullable|array',
            'occurred_at' => 'nullable|date',
        ]);

        // Verify session belongs to store if provided
        if (isset($validated['pos_session_id'])) {
            $session = PosSession::find($validated['pos_session_id']);
            if (!$session || $session->store_id !== $store->id) {
                return response()->json(['message' => 'Session not found or does not belong to this store'], 404);
            }
        }

        $event = PosEvent::create([
            'store_id' => $store->id,
            'pos_device_id' => $validated['pos_device_id'] ?? null,
            'pos_session_id' => $validated['pos_session_id'] ?? null,
            'user_id' => $request->user()->id,
            'event_code' => $validated['event_code'],
            'event_type' => $validated['event_type'],
            'description' => $validated['description'] ?? null,
            'related_charge_id' => $validated['related_charge_id'] ?? null,
            'event_data' => $validated['event_data'] ?? null,
            'occurred_at' => $validated['occurred_at'] ?? now(),
        ]);

        return response()->json([
            'message' => 'Event created successfully',
            'event' => $this->formatEventResponse($event->load(['posDevice', 'posSession', 'user', 'relatedCharge'])),
        ], 201);
    }

    /**
     * Get a specific event
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $event = PosEvent::where('id', $id)
            ->where('store_id', $store->id)
            ->with(['posDevice', 'posSession', 'user', 'relatedCharge'])
            ->firstOrFail();

        return response()->json([
            'event' => $this->formatEventResponse($event),
        ]);
    }

    /**
     * Format event for API response
     */
    protected function formatEventResponse(PosEvent $event): array
    {
        return [
            'id' => $event->id,
            'event_code' => $event->event_code,
            'event_type' => $event->event_type,
            'description' => $event->description,
            'event_description' => $event->event_description,
            'store_id' => $event->store_id,
            'pos_device_id' => $event->pos_device_id,
            'pos_device' => $event->posDevice ? [
                'id' => $event->posDevice->id,
                'device_name' => $event->posDevice->device_name,
            ] : null,
            'pos_session_id' => $event->pos_session_id,
            'pos_session' => $event->posSession ? [
                'id' => $event->posSession->id,
                'session_number' => $event->posSession->session_number,
            ] : null,
            'user_id' => $event->user_id,
            'user' => $event->user ? [
                'id' => $event->user->id,
                'name' => $event->user->name,
            ] : null,
            'related_charge_id' => $event->related_charge_id,
            'event_data' => $event->event_data,
            'occurred_at' => $event->occurred_at->toISOString(),
            'created_at' => $event->created_at->toISOString(),
        ];
    }
}
