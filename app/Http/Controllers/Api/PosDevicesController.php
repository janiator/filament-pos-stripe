<?php

namespace App\Http\Controllers\Api;

use App\Models\PosDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            ->with(['terminalLocations.terminalReaders', 'receiptPrinters', 'lastConnectedTerminalLocation', 'lastConnectedTerminalReader'])
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
            'device_identifier' => [
                'required',
                'string',
                Rule::unique('pos_devices', 'device_identifier')->where('store_id', $store->id),
            ],
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
            ->with(['terminalLocations.terminalReaders', 'receiptPrinters', 'lastConnectedTerminalLocation', 'lastConnectedTerminalReader'])
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
            'default_printer_id' => 'nullable|exists:receipt_printers,id',
            'last_connected_terminal_location_id' => 'nullable|exists:terminal_locations,id',
            'last_connected_terminal_reader_id' => 'nullable|exists:terminal_readers,id',
        ]);

        // Validate that the printer belongs to the same store
        if (isset($validated['default_printer_id'])) {
            $printer = \App\Models\ReceiptPrinter::where('id', $validated['default_printer_id'])
                ->where('store_id', $store->id)
                ->first();

            if (!$printer) {
                return response()->json([
                    'message' => 'Receipt printer not found or does not belong to this store',
                ], 422);
            }
        }

        // Validate that last-connected terminal location and reader belong to this store
        if (array_key_exists('last_connected_terminal_location_id', $validated) && $validated['last_connected_terminal_location_id']) {
            $loc = \App\Models\TerminalLocation::where('id', $validated['last_connected_terminal_location_id'])
                ->where('store_id', $store->id)
                ->first();
            if (!$loc) {
                return response()->json([
                    'message' => 'Terminal location not found or does not belong to this store',
                ], 422);
            }
        }
        if (array_key_exists('last_connected_terminal_reader_id', $validated) && $validated['last_connected_terminal_reader_id']) {
            $reader = \App\Models\TerminalReader::where('id', $validated['last_connected_terminal_reader_id'])
                ->where('store_id', $store->id)
                ->first();
            if (!$reader) {
                return response()->json([
                    'message' => 'Terminal reader not found or does not belong to this store',
                ], 422);
            }
        }

        $device->update($validated);
        $device->load(['lastConnectedTerminalLocation', 'lastConnectedTerminalReader']);

        return response()->json([
            'message' => 'POS device updated successfully',
            'device' => $this->formatDeviceResponse($device),
        ]);
    }

    /**
     * Update device heartbeat (last_seen_at and optional status/metadata)
     * Automatically creates a start event if device was inactive for a while
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

        // Store the old last_seen_at value before updating (needed for inactivity calculation)
        $oldLastSeenAt = $device->last_seen_at;

        // Check if device was inactive (no heartbeat for more than 10 minutes)
        // If so, create a start event to indicate the device came back online
        $inactivityThreshold = now()->subMinutes(10);
        $wasInactive = $oldLastSeenAt === null || $oldLastSeenAt < $inactivityThreshold;
        
        // Check if there's already a recent start event (within last 30 seconds)
        // to avoid creating duplicate events if multiple heartbeats arrive quickly
        $recentStartEvent = null;
        if ($wasInactive) {
            $recentStartEvent = \App\Models\PosEvent::where('pos_device_id', $device->id)
                ->where('event_code', \App\Models\PosEvent::EVENT_APPLICATION_START)
                ->where('occurred_at', '>=', now()->subSeconds(30))
                ->first();
        }

        $validated['last_seen_at'] = now();
        $validated['device_status'] = $validated['device_status'] ?? 'active';

        $device->update($validated);

        $startEventCreated = false;
        $startEventId = null;

        // Create start event if device was inactive and no recent start event exists
        if ($wasInactive && !$recentStartEvent) {
            // Get current session if exists
            $currentSession = \App\Models\PosSession::where('store_id', $store->id)
                ->where('pos_device_id', $device->id)
                ->where('status', 'open')
                ->first();

            // Get user ID (may be null if app starts before login)
            $userId = $request->user()?->id;

            // Calculate inactivity duration using the old last_seen_at value
            $inactivityDuration = $oldLastSeenAt 
                ? round($oldLastSeenAt->diffInMinutes(now()), 2)
                : null;

            // Log application start event (13001)
            $event = \App\Models\PosEvent::create([
                'store_id' => $store->id,
                'pos_device_id' => $device->id,
                'pos_session_id' => $currentSession?->id,
                'user_id' => $userId,
                'event_code' => \App\Models\PosEvent::EVENT_APPLICATION_START,
                'event_type' => 'application',
                'description' => "POS application resumed on device {$device->device_name} after inactivity",
                'event_data' => [
                    'device_name' => $device->device_name,
                    'platform' => $device->platform,
                    'system_version' => $device->system_version,
                    'user_logged_in' => !is_null($userId),
                    'inactivity_duration_minutes' => $inactivityDuration,
                    'auto_detected' => true,
                ],
                'occurred_at' => now(),
            ]);

            $startEventCreated = true;
            $startEventId = $event->id;
        }

        $response = [
            'message' => 'Device heartbeat updated',
            'device' => $this->formatDeviceResponse($device->fresh()),
        ];

        if ($startEventCreated) {
            $response['start_event_created'] = true;
            $response['start_event_id'] = $startEventId;
            $response['message'] = 'Device heartbeat updated and start event created (device resumed after inactivity)';
        }

        return response()->json($response);
    }

    /**
     * Log POS application start (13001)
     */
    public function start(Request $request, string $id): JsonResponse
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

        // Get current session if exists
        $currentSession = \App\Models\PosSession::where('store_id', $store->id)
            ->where('pos_device_id', $device->id)
            ->where('status', 'open')
            ->first();

        // Check for recent start event to prevent duplicates (within last 30 seconds)
        $recentStartEvent = \App\Models\PosEvent::where('pos_device_id', $device->id)
            ->where('event_code', \App\Models\PosEvent::EVENT_APPLICATION_START)
            ->where('occurred_at', '>=', now()->subSeconds(30))
            ->first();

        if ($recentStartEvent) {
            // Return existing event info instead of creating duplicate
            return response()->json([
                'message' => 'Application start already logged recently',
                'device' => $this->formatDeviceResponse($device),
                'current_session' => $currentSession ? [
                    'id' => $currentSession->id,
                    'session_number' => $currentSession->session_number,
                ] : null,
                'warning' => 'Start event was logged less than 30 seconds ago',
            ]);
        }

        // Update device status and last seen timestamp
        $device->update([
            'device_status' => 'active',
            'last_seen_at' => now(),
        ]);

        // Get user ID (may be null if app starts before login)
        $userId = $request->user()?->id;

        // Log application start event (13001)
        $event = \App\Models\PosEvent::create([
            'store_id' => $store->id,
            'pos_device_id' => $device->id,
            'pos_session_id' => $currentSession?->id,
            'user_id' => $userId,
            'event_code' => \App\Models\PosEvent::EVENT_APPLICATION_START,
            'event_type' => 'application',
            'description' => "POS application started on device {$device->device_name}",
            'event_data' => [
                'device_name' => $device->device_name,
                'platform' => $device->platform,
                'system_version' => $device->system_version,
                'user_logged_in' => !is_null($userId),
            ],
            'occurred_at' => now(),
        ]);

        return response()->json([
            'message' => 'Application start logged successfully',
            'device' => $this->formatDeviceResponse($device->fresh()),
            'current_session' => $currentSession ? [
                'id' => $currentSession->id,
                'session_number' => $currentSession->session_number,
            ] : null,
            'event_id' => $event->id,
        ]);
    }

    /**
     * Log POS application shutdown (13002)
     */
    public function shutdown(Request $request, string $id): JsonResponse
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

        // Get current session if exists
        $currentSession = \App\Models\PosSession::where('store_id', $store->id)
            ->where('pos_device_id', $device->id)
            ->where('status', 'open')
            ->first();

        // Get user ID (may be null if app crashes before logout)
        $userId = $request->user()?->id;

        // Update device status to offline
        $device->update([
            'device_status' => 'offline',
            'last_seen_at' => now(),
        ]);

        // Log application shutdown event (13002)
        $event = \App\Models\PosEvent::create([
            'store_id' => $store->id,
            'pos_device_id' => $device->id,
            'pos_session_id' => $currentSession?->id,
            'user_id' => $userId,
            'event_code' => \App\Models\PosEvent::EVENT_APPLICATION_SHUTDOWN,
            'event_type' => 'application',
            'description' => "POS application shut down on device {$device->device_name}",
            'event_data' => [
                'device_name' => $device->device_name,
                'platform' => $device->platform,
                'has_open_session' => !is_null($currentSession),
                'session_id' => $currentSession?->id,
                'user_logged_in' => !is_null($userId),
            ],
            'occurred_at' => now(),
        ]);

        $response = [
            'message' => 'Application shutdown logged successfully',
            'device' => $this->formatDeviceResponse($device->fresh()),
            'event_id' => $event->id,
        ];

        // Warn if there's an open session
        if ($currentSession) {
            $response['warning'] = 'Device has an open session that should be closed';
            $response['open_session'] = [
                'id' => $currentSession->id,
                'session_number' => $currentSession->session_number,
                'opened_at' => $this->formatDateTimeOslo($currentSession->opened_at),
            ];
        }

        return response()->json($response);
    }

    /**
     * Open cash drawer (13005)
     */
    public function openCashDrawer(Request $request, string $id): JsonResponse
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
            'pos_session_id' => 'nullable|exists:pos_sessions,id',
            'related_charge_id' => 'nullable|exists:connected_charges,id',
            'nullinnslag' => 'nullable|boolean', // Drawer open without sale
            'reason' => 'nullable|string|max:500', // Reason for nullinnslag
        ]);

        // Get current session if not provided
        $session = null;
        if (isset($validated['pos_session_id'])) {
            $session = \App\Models\PosSession::where('id', $validated['pos_session_id'])
                ->where('store_id', $store->id)
                ->first();
        } else {
            $session = \App\Models\PosSession::where('store_id', $store->id)
                ->where('pos_device_id', $device->id)
                ->where('status', 'open')
                ->first();
        }

        // If nullinnslag (drawer open without sale), session is required
        $isNullinnslag = $validated['nullinnslag'] ?? false;
        if ($isNullinnslag && !$session) {
            return response()->json([
                'message' => 'Active session required for nullinnslag (drawer open without sale)',
            ], 400);
        }

        // Log cash drawer open event (13005)
        \App\Models\PosEvent::create([
            'store_id' => $store->id,
            'pos_device_id' => $device->id,
            'pos_session_id' => $session?->id,
            'user_id' => $request->user()->id,
            'related_charge_id' => $validated['related_charge_id'] ?? null,
            'event_code' => \App\Models\PosEvent::EVENT_CASH_DRAWER_OPEN,
            'event_type' => 'drawer',
            'description' => $isNullinnslag 
                ? "Cash drawer opened without sale (nullinnslag)" 
                : "Cash drawer opened",
            'event_data' => [
                'nullinnslag' => $isNullinnslag,
                'reason' => $validated['reason'] ?? null,
                'has_related_charge' => !empty($validated['related_charge_id']),
            ],
            'occurred_at' => now(),
        ]);

        return response()->json([
            'message' => 'Cash drawer open logged successfully',
            'event' => [
                'nullinnslag' => $isNullinnslag,
                'session_id' => $session?->id,
            ],
        ]);
    }

    /**
     * Close cash drawer (13006)
     */
    public function closeCashDrawer(Request $request, string $id): JsonResponse
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
            'pos_session_id' => 'nullable|exists:pos_sessions,id',
        ]);

        // Get current session if not provided
        $session = null;
        if (isset($validated['pos_session_id'])) {
            $session = \App\Models\PosSession::where('id', $validated['pos_session_id'])
                ->where('store_id', $store->id)
                ->first();
        } else {
            $session = \App\Models\PosSession::where('store_id', $store->id)
                ->where('pos_device_id', $device->id)
                ->where('status', 'open')
                ->first();
        }

        // Log cash drawer close event (13006)
        \App\Models\PosEvent::create([
            'store_id' => $store->id,
            'pos_device_id' => $device->id,
            'pos_session_id' => $session?->id,
            'user_id' => $request->user()->id,
            'event_code' => \App\Models\PosEvent::EVENT_CASH_DRAWER_CLOSE,
            'event_type' => 'drawer',
            'description' => "Cash drawer closed",
            'event_data' => [],
            'occurred_at' => now(),
        ]);

        return response()->json([
            'message' => 'Cash drawer close logged successfully',
        ]);
    }

    /**
     * Format device response with all information
     */
    /**
     * Format last-connected terminal for API response (for auto-reconnect)
     */
    protected function formatLastConnectedTerminal(PosDevice $device): ?array
    {
        $location = $device->lastConnectedTerminalLocation;
        $reader = $device->lastConnectedTerminalReader;
        if (!$location && !$reader) {
            return null;
        }
        return [
            'location_id' => $location?->id,
            'stripe_location_id' => $location?->stripe_location_id,
            'location_display_name' => $location?->display_name,
            'reader_id' => $reader?->id,
            'stripe_reader_id' => $reader?->stripe_reader_id,
            'reader_label' => $reader?->label,
        ];
    }

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
            'last_seen_at' => $this->formatDateTimeOslo($device->last_seen_at),
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
            'receipt_printers_count' => $device->receiptPrinters->count(),
            'default_printer_id' => $device->default_printer_id,
            'last_connected' => $this->formatLastConnectedTerminal($device),
            'receipt_printers' => $device->receiptPrinters->map(function ($printer) {
                return [
                    'id' => $printer->id,
                    'name' => $printer->name,
                    'printer_type' => $printer->printer_type,
                    'printer_model' => $printer->printer_model,
                    'connection_type' => $printer->connection_type,
                    'ip_address' => $printer->ip_address,
                    'port' => $printer->port,
                    'is_active' => $printer->is_active,
                    'epos_url' => $printer->isNetworkPrinter() ? $printer->epos_url : null,
                ];
            }),
            'created_at' => $this->formatDateTimeOslo($device->created_at),
            'updated_at' => $this->formatDateTimeOslo($device->updated_at),
        ];
    }
}
