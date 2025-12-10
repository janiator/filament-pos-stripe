<?php

namespace App\Http\Controllers\Api;

use App\Models\PosSession;
use App\Models\PosSessionClosing;
use App\Models\PosDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosSessionsController extends BaseApiController
{
    /**
     * Get all sessions for the current store
     */
    public function index(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $query = PosSession::where('store_id', $store->id)
            ->with(['posDevice', 'user']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by date
        if ($request->has('date')) {
            $query->whereDate('opened_at', $request->get('date'));
        }

        // Filter by device
        if ($request->has('pos_device_id')) {
            $query->where('pos_device_id', $request->get('pos_device_id'));
        }

        $sessions = $query->orderBy('opened_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'sessions' => $sessions->getCollection()->map(function ($session) {
                return $this->formatSessionResponse($session);
            }),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    /**
     * Get current open session for a device
     */
    public function current(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'pos_device_id' => 'required|exists:pos_devices,id',
        ]);

        $session = PosSession::where('store_id', $store->id)
            ->where('pos_device_id', $validated['pos_device_id'])
            ->where('status', 'open')
            ->with(['posDevice', 'user', 'charges'])
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'No open session found',
            ], 404);
        }

        return response()->json(
            $this->formatSessionResponse($session, true)
        );
    }

    /**
     * Open a new POS session
     */
    public function open(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'pos_device_id' => 'required|exists:pos_devices,id',
            'opening_balance' => 'nullable|integer|min:0',
            'opening_notes' => 'nullable|string|max:1000',
            'opening_data' => 'nullable|array',
        ]);

        // Check if device already has an open session
        $existingSession = PosSession::where('store_id', $store->id)
            ->where('pos_device_id', $validated['pos_device_id'])
            ->where('status', 'open')
            ->first();

        if ($existingSession) {
            return response()->json([
                'message' => 'Device already has an open session',
                'session' => $this->formatSessionResponse($existingSession),
            ], 409);
        }

        // Get next session number for this store
        $lastSession = PosSession::where('store_id', $store->id)
            ->orderBy('session_number', 'desc')
            ->first();

        $sessionNumber = $lastSession 
            ? (int) $lastSession->session_number + 1 
            : 1;

        $session = PosSession::create([
            'store_id' => $store->id,
            'pos_device_id' => $validated['pos_device_id'],
            'user_id' => $request->user()->id,
            'session_number' => str_pad($sessionNumber, 6, '0', STR_PAD_LEFT),
            'status' => 'open',
            'opened_at' => now(),
            'opening_balance' => $validated['opening_balance'] ?? 0,
            'opening_notes' => $validated['opening_notes'] ?? null,
            'opening_data' => $validated['opening_data'] ?? null,
        ]);

        return response()->json([
            'message' => 'Session opened successfully',
            'session' => $this->formatSessionResponse($session->load(['posDevice', 'user'])),
        ], 201);
    }

    /**
     * Close a POS session (automatically generates Z-report)
     */
    public function close(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $session = PosSession::where('id', $id)
            ->where('store_id', $store->id)
            ->with(['posDevice', 'user', 'charges', 'events', 'receipts', 'store'])
            ->firstOrFail();

        if (!$session->canBeClosed()) {
            return response()->json([
                'message' => 'Session cannot be closed',
            ], 400);
        }

        $validated = $request->validate([
            'actual_cash' => 'nullable|integer|min:0',
            'closing_notes' => 'nullable|string|max:1000',
            'closing_data' => 'nullable|array',
        ]);

        // Close session
        $session->close(
            $validated['actual_cash'] ?? null,
            $validated['closing_notes'] ?? null
        );

        if (isset($validated['closing_data'])) {
            $session->closing_data = $validated['closing_data'];
            $session->save();
        }

        // Generate Z-report data first (using shared method from PosSessionsTable)
        $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);
        $report = \App\Filament\Resources\PosSessions\Tables\PosSessionsTable::generateZReport($session);
        
        // Convert dates to ISO format for API response
        $report['opened_at'] = $this->formatDateTimeOslo($session->opened_at);
        $report['closed_at'] = $session->closed_at ? $this->formatDateTimeOslo($session->closed_at) : null;
        $report['report_generated_at'] = $this->formatDateTimeOslo($report['report_generated_at']);
        
        // Log Z-report event (13009) with complete report data for electronic journal compliance
        \App\Models\PosEvent::create([
            'store_id' => $store->id,
            'pos_device_id' => $session->pos_device_id,
            'pos_session_id' => $session->id,
            'user_id' => $request->user()->id,
            'event_code' => \App\Models\PosEvent::EVENT_Z_REPORT,
            'event_type' => 'report',
            'description' => "Z-report for session {$session->session_number}",
            'event_data' => [
                'report_type' => 'Z-Report',
                'session_number' => $session->session_number,
                'report_data' => $report, // Complete report data for electronic journal
            ],
            'occurred_at' => now(),
        ]);

        return response()->json([
            'message' => 'Session closed successfully',
            'session' => $this->formatSessionResponse($session->fresh(['posDevice', 'user', 'charges'])),
            'report' => $report,
        ]);
    }

    /**
     * Get a specific session
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $session = PosSession::where('id', $id)
            ->where('store_id', $store->id)
            ->with(['posDevice', 'user', 'charges'])
            ->firstOrFail();

        return response()->json([
            'session' => $this->formatSessionResponse($session, true),
        ]);
    }

    /**
     * Generate X-report (current session summary)
     */
    public function xReport(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $session = PosSession::where('id', $id)
            ->where('store_id', $store->id)
            ->with(['charges', 'posDevice', 'user', 'events'])
            ->firstOrFail();

        // X-reports should only be generated for open sessions per kassasystemforskriften § 2-8-2
        if ($session->status !== 'open') {
            return response()->json([
                'message' => 'X-report can only be generated for open sessions. Use Z-report for closed sessions.',
            ], 400);
        }

        // Generate report data first (using shared method from PosSessionsTable)
        $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);
        $report = \App\Filament\Resources\PosSessions\Tables\PosSessionsTable::generateXReport($session);
        
        // Convert dates to ISO format for API response
        $report['opened_at'] = $this->formatDateTimeOslo($session->opened_at);
        $report['report_generated_at'] = $this->formatDateTimeOslo($report['report_generated_at']);
        
        // Log X-report event (13008) with complete report data for electronic journal compliance
        \App\Models\PosEvent::create([
            'store_id' => $store->id,
            'pos_device_id' => $session->pos_device_id,
            'pos_session_id' => $session->id,
            'user_id' => $request->user()->id,
            'event_code' => \App\Models\PosEvent::EVENT_X_REPORT,
            'event_type' => 'report',
            'description' => "X-report for session {$session->session_number}",
            'event_data' => [
                'report_type' => 'X-Report',
                'session_number' => $session->session_number,
                'report_data' => $report, // Complete report data for electronic journal
            ],
            'occurred_at' => now(),
        ]);

        return response()->json([
            'message' => 'X-report generated successfully',
            'report' => $report,
        ]);
    }

    /**
     * Generate Z-report (end-of-day report, closes session)
     */
    public function zReport(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $session = PosSession::where('id', $id)
            ->where('store_id', $store->id)
            ->with(['charges', 'posDevice', 'user', 'events', 'receipts'])
            ->firstOrFail();

        if (!$session->canBeClosed()) {
            return response()->json([
                'message' => 'Session cannot be closed',
            ], 400);
        }

        $validated = $request->validate([
            'actual_cash' => 'nullable|integer|min:0',
            'closing_notes' => 'nullable|string|max:1000',
        ]);

        $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);
        $charges = $session->charges->where('status', 'succeeded');

        // Close session
        $session->close(
            $validated['actual_cash'] ?? null,
            $validated['closing_notes'] ?? null
        );

        // Generate Z-report data first (using shared method from PosSessionsTable)
        $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);
        $report = \App\Filament\Resources\PosSessions\Tables\PosSessionsTable::generateZReport($session);
        
        // Convert dates to ISO format for API response
        $report['opened_at'] = $this->formatDateTimeOslo($session->opened_at);
        $report['closed_at'] = $session->closed_at ? $this->formatDateTimeOslo($session->closed_at) : null;
        $report['report_generated_at'] = $this->formatDateTimeOslo($report['report_generated_at']);
        
        // Log Z-report event (13009) with complete report data for electronic journal compliance
        \App\Models\PosEvent::create([
            'store_id' => $store->id,
            'pos_device_id' => $session->pos_device_id,
            'pos_session_id' => $session->id,
            'user_id' => $request->user()->id,
            'event_code' => \App\Models\PosEvent::EVENT_Z_REPORT,
            'event_type' => 'report',
            'description' => "Z-report for session {$session->session_number}",
            'event_data' => [
                'report_type' => 'Z-Report',
                'session_number' => $session->session_number,
                'report_data' => $report, // Complete report data for electronic journal
            ],
            'occurred_at' => now(),
        ]);

        return response()->json([
            'message' => 'Z-report generated and session closed',
            'session' => $this->formatSessionResponse($session->fresh(['posDevice', 'user', 'charges'])),
            'report' => $report,
        ]);
    }

    /**
     * Generate Z-report data for a closed session
     */
    protected function generateZReportData(PosSession $session): array
    {
        // Ensure all relationships are loaded
        $session->loadMissing(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);
        
        $charges = $session->charges->where('status', 'succeeded');
        $totalAmount = $charges->sum('amount');
        $cashAmount = $charges->where('payment_method', 'cash')->sum('amount');
        $cardAmount = $charges->where('payment_method', 'card')->sum('amount');
        $mobileAmount = $charges->where('payment_method', 'mobile')->sum('amount');
        $otherAmount = $totalAmount - $cashAmount - $cardAmount - $mobileAmount;
        $totalTips = $charges->sum('tip_amount');
        
        // Calculate VAT (25% standard in Norway)
        $vatRate = 0.25;
        $vatBase = round($totalAmount / (1 + $vatRate), 0);
        $vatAmount = $totalAmount - $vatBase;
        
        // Cash drawer events
        $cashDrawerOpens = $session->events->where('event_code', \App\Models\PosEvent::EVENT_CASH_DRAWER_OPEN)->count();
        $nullinnslagCount = $session->events->where('event_code', \App\Models\PosEvent::EVENT_CASH_DRAWER_OPEN)
            ->where('event_data->nullinnslag', true)->count();
        
        // Event summary
        $eventSummary = $session->events->groupBy('event_code')->map(function ($group) {
            $firstEvent = $group->first();
            return [
                'code' => $firstEvent->event_code,
                'description' => $firstEvent->event_description,
                'count' => $group->count(),
            ];
        });
        
        // Receipt summary
        $receiptSummary = $session->receipts->groupBy('receipt_type')->map(function ($group) {
            return [
                'type' => $group->first()->receipt_type,
                'count' => $group->count(),
            ];
        });

        return [
            'session_id' => $session->id,
            'session_number' => $session->session_number,
            'opened_at' => $this->formatDateTimeOslo($session->opened_at),
            'closed_at' => $this->formatDateTimeOslo($session->closed_at),
            'store' => [
                'id' => $session->store->id,
                'name' => $session->store->name,
            ],
            'device' => $session->posDevice ? [
                'id' => $session->posDevice->id,
                'name' => $session->posDevice->device_name,
            ] : null,
            'cashier' => $session->user ? [
                'id' => $session->user->id,
                'name' => $session->user->name,
            ] : null,
            'opening_balance' => $session->opening_balance,
            'expected_cash' => $session->expected_cash,
            'actual_cash' => $session->actual_cash,
            'cash_difference' => $session->cash_difference,
            'closing_notes' => $session->closing_notes,
            'transactions_count' => $charges->count(),
            'total_amount' => $totalAmount,
            'vat_base' => $vatBase,
            'vat_amount' => $vatAmount,
            'vat_rate' => $vatRate * 100,
            'cash_amount' => $cashAmount,
            'card_amount' => $cardAmount,
            'mobile_amount' => $mobileAmount,
            'other_amount' => $otherAmount,
            'total_tips' => $totalTips,
            'by_payment_method' => $this->calculateByPaymentMethod($charges),
            'by_payment_code' => $charges->groupBy('payment_code')->map(function ($group) {
                return [
                    'code' => $group->first()->payment_code,
                    'count' => $group->count(),
                    'amount' => $group->sum('amount'),
                ];
            }),
            'transactions_by_type' => $charges->groupBy('transaction_code')->map(function ($group) {
                return [
                    'code' => $group->first()->transaction_code,
                    'count' => $group->count(),
                    'amount' => $group->sum('amount'),
                ];
            }),
            'cash_drawer_opens' => $cashDrawerOpens,
            'nullinnslag_count' => $nullinnslagCount,
            'receipt_count' => $session->receipts->count(),
            'receipt_summary' => $receiptSummary,
            'event_summary' => $eventSummary,
            'complete_transaction_list' => $charges->map(function ($charge) {
                return [
                    'id' => $charge->id,
                    'stripe_charge_id' => $charge->stripe_charge_id,
                    'amount' => $charge->amount,
                    'currency' => $charge->currency,
                    'payment_method' => $charge->payment_method,
                    'payment_code' => $charge->payment_code,
                    'transaction_code' => $charge->transaction_code,
                    'tip_amount' => $charge->tip_amount,
                    'description' => $charge->description,
                    'paid_at' => $this->formatDateTimeOslo($charge->paid_at),
                    'created_at' => $this->formatDateTimeOslo($charge->created_at),
                ];
            }),
        ];
    }

    /**
     * Calculate totals by payment method
     */
    protected function calculateByPaymentMethod($charges): array
    {
        $byMethod = [];
        foreach ($charges as $charge) {
            $method = $charge->payment_method ?? 'unknown';
            if (!isset($byMethod[$method])) {
                $byMethod[$method] = [
                    'count' => 0,
                    'amount' => 0,
                ];
            }
            $byMethod[$method]['count']++;
            $byMethod[$method]['amount'] += $charge->amount;
        }
        return $byMethod;
    }

    /**
     * Create daily closing report
     */
    public function createDailyClosing(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'closing_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $closingDate = $validated['closing_date'];

        // Check if closing already exists for this date
        $existingClosing = PosSessionClosing::where('store_id', $store->id)
            ->where('closing_date', $closingDate)
            ->first();

        if ($existingClosing) {
            return response()->json([
                'message' => 'Daily closing already exists for this date',
                'closing' => $this->formatClosingResponse($existingClosing),
            ], 409);
        }

        // Get all closed sessions for this date
        $sessions = PosSession::where('store_id', $store->id)
            ->whereDate('closed_at', $closingDate)
            ->where('status', 'closed')
            ->with('charges')
            ->get();

        // Calculate totals
        $totalSessions = $sessions->count();
        $totalTransactions = 0;
        $totalAmount = 0;
        $totalCash = 0;
        $totalCard = 0;
        $totalRefunds = 0;

        $summaryData = [
            'by_payment_method' => [],
            'by_session' => [],
        ];

        foreach ($sessions as $session) {
            $sessionTransactions = $session->charges->where('status', 'succeeded');
            $sessionTotal = $sessionTransactions->sum('amount');
            $sessionCash = $sessionTransactions->where('payment_method', 'cash')->sum('amount');
            $sessionCard = $sessionTransactions->where('payment_method', '!=', 'cash')->sum('amount');
            $sessionRefunds = $sessionTransactions->sum('amount_refunded');

            $totalTransactions += $sessionTransactions->count();
            $totalAmount += $sessionTotal;
            $totalCash += $sessionCash;
            $totalCard += $sessionCard;
            $totalRefunds += $sessionRefunds;

            $summaryData['by_session'][] = [
                'session_id' => $session->id,
                'session_number' => $session->session_number,
                'transactions' => $sessionTransactions->count(),
                'amount' => $sessionTotal,
                'cash' => $sessionCash,
                'card' => $sessionCard,
                'refunds' => $sessionRefunds,
            ];
        }

        // Calculate by payment method
        foreach ($sessions as $session) {
            foreach ($session->charges->where('status', 'succeeded') as $charge) {
                $method = $charge->payment_method ?? 'unknown';
                if (!isset($summaryData['by_payment_method'][$method])) {
                    $summaryData['by_payment_method'][$method] = [
                        'count' => 0,
                        'amount' => 0,
                    ];
                }
                $summaryData['by_payment_method'][$method]['count']++;
                $summaryData['by_payment_method'][$method]['amount'] += $charge->amount;
            }
        }

        $closing = PosSessionClosing::create([
            'store_id' => $store->id,
            'closing_date' => $closingDate,
            'closed_by_user_id' => $request->user()->id,
            'closed_at' => now(),
            'total_sessions' => $totalSessions,
            'total_transactions' => $totalTransactions,
            'total_amount' => $totalAmount,
            'total_cash' => $totalCash,
            'total_card' => $totalCard,
            'total_refunds' => $totalRefunds,
            'currency' => 'nok',
            'summary_data' => $summaryData,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Daily closing created successfully',
            'closing' => $this->formatClosingResponse($closing->load('closedByUser')),
        ], 201);
    }

    /**
     * Format session response
     */
    protected function formatSessionResponse(PosSession $session, bool $includeCharges = false): array
    {
        $data = [
            'id' => $session->id,
            'session_number' => $session->session_number,
            'status' => $session->status,
            'opened_at' => $this->formatDateTimeOslo($session->opened_at),
            'closed_at' => $this->formatDateTimeOslo($session->closed_at),
            'opening_balance' => $session->opening_balance,
            'expected_cash' => $session->expected_cash,
            'actual_cash' => $session->actual_cash,
            'cash_difference' => $session->cash_difference,
            'opening_notes' => $session->opening_notes,
            'closing_notes' => $session->closing_notes,
            'session_device' => $session->posDevice ? [
                'id' => $session->posDevice->id,
                'device_name' => $session->posDevice->device_name,
            ] : null,
            'session_user' => $session->user ? [
                'id' => $session->user->id,
                'name' => $session->user->name,
            ] : null,
            'transaction_count' => $session->transaction_count,
            'total_amount' => $session->total_amount,
        ];

        if ($includeCharges) {
            $data['session_charges'] = $session->charges->map(function ($charge) {
                return [
                    'id' => $charge->id,
                    'stripe_charge_id' => $charge->stripe_charge_id,
                    'amount' => $charge->amount,
                    'currency' => $charge->currency,
                    'status' => $charge->status,
                    'payment_method' => $charge->payment_method,
                    'paid_at' => $this->formatDateTimeOslo($charge->paid_at),
                ];
            });
        }

        return $data;
    }

    /**
     * Format closing response
     */
    protected function formatClosingResponse(PosSessionClosing $closing): array
    {
        return [
            'id' => $closing->id,
            'closing_date' => $closing->closing_date->format('Y-m-d'),
            'closed_at' => $this->formatDateTimeOslo($closing->closed_at),
            'total_sessions' => $closing->total_sessions,
            'total_transactions' => $closing->total_transactions,
            'total_amount' => $closing->total_amount,
            'total_cash' => $closing->total_cash,
            'total_card' => $closing->total_card,
            'total_refunds' => $closing->total_refunds,
            'currency' => $closing->currency,
            'summary_data' => $closing->summary_data,
            'notes' => $closing->notes,
            'verified' => $closing->verified,
            'closed_by_user' => $closing->closedByUser ? [
                'id' => $closing->closedByUser->id,
                'name' => $closing->closedByUser->name,
            ] : null,
            'verified_by_user' => $closing->verifiedByUser ? [
                'id' => $closing->verifiedByUser->id,
                'name' => $closing->verifiedByUser->name,
            ] : null,
            'verified_at' => $this->formatDateTimeOslo($closing->verified_at),
        ];
    }
}
