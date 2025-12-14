<?php

namespace App\Http\Controllers\Api;

use App\Models\Receipt;
use App\Models\ConnectedCharge;
use App\Services\ReceiptGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptsController extends BaseApiController
{
    protected ReceiptGenerationService $receiptService;

    public function __construct(ReceiptGenerationService $receiptService)
    {
        $this->receiptService = $receiptService;
    }

    /**
     * List receipts for the current store
     */
    public function index(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $query = Receipt::where('store_id', $store->id)
            ->with(['posSession', 'charge', 'user', 'originalReceipt']);

        // Filter by receipt type
        if ($request->has('receipt_type')) {
            $query->where('receipt_type', $request->get('receipt_type'));
        }

        // Filter by session
        if ($request->has('pos_session_id')) {
            $query->where('pos_session_id', $request->get('pos_session_id'));
        }

        // Filter by charge (purchase)
        if ($request->has('charge_id')) {
            $query->where('charge_id', $request->get('charge_id'));
        }

        // Filter by printed status
        if ($request->has('printed')) {
            $query->where('printed', $request->boolean('printed'));
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->get('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->get('to_date'));
        }

        $receipts = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        // Get charge IDs from receipts to fetch available types
        $chargeIds = $receipts->getCollection()
            ->pluck('charge_id')
            ->filter()
            ->unique()
            ->toArray();

        // Pre-load charge data and correction events for all charges
        $charges = [];
        $correctionEvents = [];
        if (!empty($chargeIds)) {
            $charges = ConnectedCharge::whereIn('id', $chargeIds)
                ->get()
                ->keyBy('id');
            
            // Check for correction events/receipts for these charges
            $correctionEvents = \App\Models\PosEvent::whereIn('related_charge_id', $chargeIds)
                ->where('event_code', \App\Models\PosEvent::EVENT_CORRECTION_RECEIPT)
                ->pluck('related_charge_id')
                ->toArray();
        }

        // Build receipt list with generated receipts
        // Refresh receipts to ensure we have the latest printed status
        $receiptList = $receipts->getCollection()->map(function ($receipt) use ($charges, $correctionEvents) {
            // Refresh receipt to ensure we have the latest printed status from database
            $receipt->refresh();
            $charge = $receipt->charge_id ? ($charges[$receipt->charge_id] ?? null) : null;
            return $this->formatReceiptListResponse($receipt, $charge, $correctionEvents);
        })->toArray();

        // Add available (non-generated) receipt types for each charge
        // Group receipts by charge_id to avoid duplicates
        $chargesWithReceipts = [];
        foreach ($receipts->getCollection() as $receipt) {
            if ($receipt->charge_id) {
                $chargesWithReceipts[$receipt->charge_id] = $receipt->charge_id;
            }
        }

        // Add available receipt types as "virtual" receipts in the list
        foreach ($chargesWithReceipts as $chargeId) {
            $charge = $charges[$chargeId] ?? null;
            $hasCorrection = in_array($chargeId, $correctionEvents) || 
                            Receipt::where('charge_id', $chargeId)
                                ->where('receipt_type', 'correction')
                                ->where('store_id', $store->id)
                                ->exists();
            
            $availableTypes = $this->getAvailableReceiptTypes($chargeId, $store->id, $charge, $hasCorrection);
            
            foreach ($availableTypes as $availableType) {
                $receiptList[] = $this->formatAvailableReceiptType($availableType);
            }
        }

        $response = [
            'simpleReceiptList' => $receiptList,
            'current_page' => $receipts->currentPage(),
            'last_page' => $receipts->lastPage(),
            'per_page' => $receipts->perPage(),
            'total' => $receipts->total(),
        ];

        return response()->json($response);
    }

    /**
     * Generate a receipt for a charge
     */
    public function generate(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'charge_id' => 'required|exists:connected_charges,id',
            'receipt_type' => 'nullable|in:sales,return,copy,steb,provisional,training,delivery',
            'pos_session_id' => 'nullable|exists:pos_sessions,id',
        ]);

        $charge = ConnectedCharge::where('id', $validated['charge_id'])
            ->where('stripe_account_id', $store->stripe_account_id)
            ->firstOrFail();

        $session = isset($validated['pos_session_id']) 
            ? \App\Models\PosSession::find($validated['pos_session_id'])
            : $charge->posSession;

        $receiptType = $validated['receipt_type'] ?? 'sales';

        if ($receiptType === 'sales') {
            $receipt = $this->receiptService->generateSalesReceipt($charge, $session);
        } else {
            // For other types, create a basic receipt
            $receipt = Receipt::create([
                'store_id' => $store->id,
                'pos_session_id' => $session?->id,
                'charge_id' => $charge->id,
                'user_id' => $request->user()->id,
                'receipt_number' => Receipt::generateReceiptNumber($store->id, $receiptType),
                'receipt_type' => $receiptType,
                'receipt_data' => [
                    'store' => [
                        'name' => $store->name,
                    ],
                    'charge_id' => $charge->stripe_charge_id,
                    'amount' => $charge->amount / 100,
                ],
            ]);
        }

        return response()->json([
            'message' => 'Receipt generated successfully',
            'receipt' => $this->formatReceiptResponse($receipt->load(['posSession', 'charge', 'user'])),
        ], 201);
    }

    /**
     * Get a specific receipt
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $receipt = Receipt::where('id', $id)
            ->where('store_id', $store->id)
            ->with(['posSession', 'charge', 'user', 'originalReceipt'])
            ->firstOrFail();

        $response = $this->formatReceiptResponse($receipt);

        // Include XML if available
        if (isset($receipt->receipt_data['xml'])) {
            $response['xml'] = $receipt->receipt_data['xml'];
        }

        return response()->json([
            'receipt' => $response,
        ]);
    }

    /**
     * Get receipt XML for printing
     * 
     * Returns the receipt XML without modifying print status.
     * According to Kassasystemforskriften § 2-8-4:
     * - If receipt is not printed: Returns original receipt XML
     * - If receipt is already printed: Returns copy receipt XML (marked as "KOPI")
     *   - Only one copy receipt allowed per original (enforced)
     *   - STEB receipts can be reprinted multiple times (exception)
     * 
     * Call mark-printed endpoint after successful print.
     */
    public function xml(Request $request, string $id): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        // First check if receipt exists
        $receipt = Receipt::where('id', $id)
            ->where('store_id', $store->id)
            ->with(['store', 'charge', 'posSession', 'user', 'originalReceipt'])
            ->first();

        if (!$receipt) {
            return response()->json(['message' => 'Receipt not found'], 404);
        }

        // If receipt is already printed, return copy receipt instead
        // According to Kassasystemforskriften § 2-8-4: original receipts can only be printed once
        if ($receipt->printed) {
            // Check if receipt can still be printed (only STEB receipts can be reprinted)
            if (!$receipt->canBePrinted()) {
                return response()->json([
                    'message' => 'Receipt cannot be reprinted. According to Kassasystemforskriften § 2-8-4, original receipts can only be printed once.',
                    'error' => 'reprint_not_allowed',
                    'receipt_type' => $receipt->receipt_type,
                    'reprint_count' => $receipt->reprint_count,
                ], 403);
            }

            // For non-STEB receipts that are already printed
            // According to § 2-8-4: original receipts can only be printed once
            // Copy and delivery receipts also follow this rule - they can only be printed once
            if ($receipt->receipt_type !== 'steb') {
                // Copy and delivery receipts cannot be reprinted - they can only be printed once
                if ($receipt->receipt_type === 'copy' || $receipt->receipt_type === 'delivery') {
                    return response()->json([
                        'message' => 'Copy and delivery receipts can only be printed once. Cannot reprint.',
                        'error' => 'reprint_not_allowed',
                        'receipt_type' => $receipt->receipt_type,
                    ], 403);
                }
                
                // For sales and return receipts, check if copy receipt exists
                // According to § 2-8-4: only one copy receipt can be printed per original
                if ($receipt->hasCopyReceipt()) {
                    // Copy receipt already exists, return it
                    $copyReceipt = Receipt::where('store_id', $store->id)
                        ->where('receipt_type', 'copy')
                        ->where('original_receipt_id', $receipt->id)
                        ->first();
                    $receipt = $copyReceipt;
                } else {
                    // Create copy receipt (only one allowed per original)
                    // Works for both sales and return receipts
                    $copyReceipt = $this->createCopyReceipt($receipt);
                    $receipt = $copyReceipt;
                }
            }
            // For STEB receipts, allow reprinting the same receipt
        }

        // Always render fresh XML using renderReceipt()
        $templateService = app(\App\Services\ReceiptTemplateService::class);
        $xml = $templateService->renderReceipt($receipt);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'inline; filename="receipt-' . $receipt->receipt_number . '.xml"',
        ]);
    }

    /**
     * Mark receipt as printed
     * 
     * Marks receipt as printed on first call, increments reprint count on subsequent calls.
     * This ensures compliance by tracking all print operations.
     */
    public function markPrinted(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $receipt = Receipt::where('id', $id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        // According to Kassasystemforskriften § 2-8-4:
        // - Original receipts can only be printed once (no reprints)
        // - Only one copy receipt can be printed per original
        // - STEB receipts can be printed multiple times (exception)
        
        if (!$receipt->printed) {
            // First print - always allowed
            $receipt->markAsPrinted();
            $message = 'Receipt marked as printed';
        } else {
            // Receipt already printed - check if reprint is allowed
            if (!$receipt->canBePrinted()) {
                return response()->json([
                    'message' => 'Receipt cannot be reprinted. According to Kassasystemforskriften § 2-8-4, original receipts can only be printed once, and only one copy receipt is allowed per original.',
                    'error' => 'reprint_not_allowed',
                    'receipt_type' => $receipt->receipt_type,
                    'reprint_count' => $receipt->reprint_count,
                ], 403);
            }
            
            // Only STEB receipts can be reprinted
            if ($receipt->receipt_type === 'steb') {
                $receipt->incrementReprint();
                $message = 'STEB receipt reprint recorded';
            } else {
                // For other types, this shouldn't happen (canBePrinted would return false)
                return response()->json([
                    'message' => 'Receipt cannot be reprinted. Original receipts can only be printed once.',
                    'error' => 'reprint_not_allowed',
                    'receipt_type' => $receipt->receipt_type,
                ], 403);
            }
        }

        return response()->json([
            'message' => $message,
            'receipt' => $this->formatReceiptResponse($receipt->load(['posSession', 'charge', 'user'])),
        ]);
    }

    /**
     * Reprint receipt
     * 
     * Enforces reprint rules based on Kassasystemforskriften § 2-8-4:
     * - Original receipts can only be printed once (no reprints)
     * - Only one copy receipt can be printed per original
     * - STEB receipts can be printed multiple times (exception for tax-free shops)
     */
    public function reprint(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $receipt = Receipt::where('id', $id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        // Check if receipt can be reprinted according to Kassasystemforskriften § 2-8-4
        if (!$receipt->canBePrinted()) {
            if ($receipt->receipt_type === 'steb') {
                $maxReprints = config('receipts.max_reprints_steb', 10);
                return response()->json([
                    'message' => 'STEB receipt has reached maximum reprint limit (' . $maxReprints . '). Cannot reprint further.',
                    'error' => 'reprint_limit_exceeded',
                    'reprint_count' => $receipt->reprint_count,
                    'max_reprints' => $maxReprints,
                ], 403);
            } else {
                return response()->json([
                    'message' => 'Receipt cannot be reprinted. According to Kassasystemforskriften § 2-8-4, original receipts can only be printed once, and only one copy receipt is allowed per original.',
                    'error' => 'reprint_not_allowed',
                    'receipt_type' => $receipt->receipt_type,
                    'reprint_count' => $receipt->reprint_count,
                ], 403);
            }
        }

        // Only STEB receipts can be reprinted
        if ($receipt->receipt_type !== 'steb') {
            return response()->json([
                'message' => 'Only STEB receipts can be reprinted. Original receipts can only be printed once.',
                'error' => 'reprint_not_allowed',
                'receipt_type' => $receipt->receipt_type,
            ], 403);
        }

        $receipt->incrementReprint();

        return response()->json([
            'message' => 'STEB receipt reprinted',
            'receipt' => $this->formatReceiptResponse($receipt->load(['posSession', 'charge', 'user'])),
        ]);
    }

    /**
     * Create a copy receipt from an original receipt
     * 
     * According to Kassasystemforskriften § 2-8-4: only one copy receipt can be printed per original
     */
    protected function createCopyReceipt(Receipt $originalReceipt): Receipt
    {
        // Check if copy receipt already exists (should not happen, but safety check)
        if ($originalReceipt->hasCopyReceipt()) {
            throw new \Exception('Copy receipt already exists for this original receipt. According to Kassasystemforskriften § 2-8-4, only one copy receipt is allowed per original.');
        }

        $store = $originalReceipt->store;
        
        // Prepare receipt data for copy - preserve all original data
        $receiptData = $originalReceipt->receipt_data;
        $receiptData['original_receipt_number'] = $originalReceipt->receipt_number;
        $receiptNumber = Receipt::generateReceiptNumber($store->id, 'copy');
        $receiptData['receipt_number'] = $receiptNumber;
        $receiptData['date'] = now()->setTimezone('Europe/Oslo')->format('Y-m-d H:i:s');

        $copyReceipt = Receipt::create([
            'store_id' => $store->id,
            'pos_session_id' => $originalReceipt->pos_session_id,
            'charge_id' => $originalReceipt->charge_id,
            'user_id' => $originalReceipt->user_id,
            'receipt_number' => $receiptNumber,
            'receipt_type' => 'copy',
            'original_receipt_id' => $originalReceipt->id,
            'receipt_data' => $receiptData,
        ]);

        // Render and save XML template
        $templateService = app(\App\Services\ReceiptTemplateService::class);
        $templateService->renderAndSave($copyReceipt);

        // Reload with relationships
        return $copyReceipt->load(['store', 'charge', 'posSession', 'user', 'originalReceipt']);
    }

    /**
     * Format receipt for list API response (simplified)
     */
    protected function formatReceiptListResponse(Receipt $receipt, ?ConnectedCharge $charge = null, array $correctionEvents = []): array
    {
        // Get Norwegian display name for receipt type
        $receiptTypeConfig = config('receipts.types.' . $receipt->receipt_type, []);
        $receiptTypeDisplayName = $receiptTypeConfig['label'] ?? $receipt->receipt_type;

        // Check if receipt can be printed according to Kassasystemforskriften § 2-8-4
        // For sales receipts: if already printed, cannot be reprinted (original can only be printed once)
        $canBePrinted = $receipt->canBePrinted();

        return [
            'id' => $receipt->id,
            'receipt_type' => $receipt->receipt_type,
            'receipt_type_display_name' => $receiptTypeDisplayName,
            'created_at' => $this->formatDateTimeOslo($receipt->created_at),
            'cashier_name' => $receipt->user?->name ?? null,
            'reprint_count' => $receipt->reprint_count,
            'can_be_printed' => $canBePrinted,
            'is_generated' => true,
        ];
    }

    /**
     * Get available receipt types that can be generated for a charge
     * 
     * Returns receipt types that are available but not yet generated,
     * with their Norwegian display names.
     * 
     * @param int $chargeId
     * @param int $storeId
     * @param ConnectedCharge|null $charge
     * @param bool $hasCorrection
     * @return array
     */
    protected function getAvailableReceiptTypes(int $chargeId, int $storeId, ?ConnectedCharge $charge = null, bool $hasCorrection = false): array
    {
        // Get all existing receipts for this charge
        $existingReceipts = Receipt::where('charge_id', $chargeId)
            ->where('store_id', $storeId)
            ->get();

        $existingTypes = $existingReceipts->pluck('receipt_type')->toArray();
        $availableTypes = [];

        // Get all receipt types from config
        $allTypes = config('receipts.types', []);

        // Check if charge is completed (paid/succeeded)
        $isCompletedSale = $charge && ($charge->status === 'succeeded' || $charge->paid === true);

        // Check if system is in production (live system)
        $isLiveSystem = app()->environment('production');

        foreach ($allTypes as $type => $config) {
            // Skip if this type already exists
            if (in_array($type, $existingTypes)) {
                continue;
            }

            // Determine if this type can be generated based on business rules
            $canGenerate = false;

            switch ($type) {
                case 'copy':
                    // Copy receipt can be generated if there's a sales receipt or return receipt
                    // According to § 2-8-4: only one copy receipt allowed per original
                    $hasSalesReceipt = in_array('sales', $existingTypes);
                    $hasReturnReceipt = in_array('return', $existingTypes);
                    
                    if ($hasSalesReceipt) {
                        $salesReceipt = $existingReceipts->firstWhere('receipt_type', 'sales');
                        // Check if copy receipt already exists for this sales receipt
                        $copyExists = Receipt::where('original_receipt_id', $salesReceipt->id)
                            ->where('receipt_type', 'copy')
                            ->exists();
                        
                        // Can generate copy if sales receipt exists and copy doesn't exist yet
                        $canGenerate = !$copyExists;
                    } elseif ($hasReturnReceipt) {
                        $returnReceipt = $existingReceipts->firstWhere('receipt_type', 'return');
                        // Check if copy receipt already exists for this return receipt
                        $copyExists = Receipt::where('original_receipt_id', $returnReceipt->id)
                            ->where('receipt_type', 'copy')
                            ->exists();
                        
                        // Can generate copy if return receipt exists and copy doesn't exist yet
                        $canGenerate = !$copyExists;
                    }
                    break;

                case 'steb':
                    // STEB receipts are not currently used
                    $canGenerate = false;
                    break;

                case 'return':
                    // Return receipts should not be available if a return has not been done yet
                    // Only show return receipt if it already exists (so a copy can be generated)
                    $canGenerate = false;
                    break;

                case 'sales':
                    // Sales receipt is typically generated automatically on purchase
                    // But can be manually generated if needed
                    $canGenerate = true;
                    break;

                case 'provisional':
                    // Provisional receipts should not be available for completed sales
                    $canGenerate = !$isCompletedSale;
                    break;

                case 'training':
                    // Training receipts should not be shown
                    $canGenerate = false;
                    break;

                case 'delivery':
                    // Delivery receipts can be generated as needed
                    $canGenerate = true;
                    break;

                case 'correction':
                    // Correction receipts should only be available if a correction has been made
                    $canGenerate = $hasCorrection;
                    break;

                default:
                    $canGenerate = false;
            }

            if ($canGenerate) {
                $availableTypes[] = [
                    'receipt_type' => $type,
                    'receipt_type_display_name' => $config['label'] ?? $type,
                ];
            }
        }

        return $availableTypes;
    }

    /**
     * Format available (non-generated) receipt type as a receipt-like object
     * 
     * @param array $availableType
     * @return array
     */
    protected function formatAvailableReceiptType(array $availableType): array
    {
        return [
            'id' => null,
            'receipt_type' => $availableType['receipt_type'],
            'receipt_type_display_name' => $availableType['receipt_type_display_name'],
            'created_at' => null,
            'cashier_name' => null,
            'reprint_count' => 0,
            'can_be_printed' => true,
            'is_generated' => false,
        ];
    }

    /**
     * Format receipt for API response (full details)
     */
    protected function formatReceiptResponse(Receipt $receipt): array
    {
        return [
            'id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'receipt_type' => $receipt->receipt_type,
            'store_id' => $receipt->store_id,
            'pos_session_id' => $receipt->pos_session_id,
            'pos_session' => $receipt->posSession ? [
                'id' => $receipt->posSession->id,
                'session_number' => $receipt->posSession->session_number,
            ] : null,
            'charge_id' => $receipt->charge_id,
            'charge' => $receipt->charge ? [
                'id' => $receipt->charge->id,
                'stripe_charge_id' => $receipt->charge->stripe_charge_id,
                'amount' => $receipt->charge->amount,
            ] : null,
            'user_id' => $receipt->user_id,
            'user' => $receipt->user ? [
                'id' => $receipt->user->id,
                'name' => $receipt->user->name,
            ] : null,
            'original_receipt_id' => $receipt->original_receipt_id,
            'receipt_data' => $receipt->receipt_data,
            'printed' => $receipt->printed,
            'printed_at' => $this->formatDateTimeOslo($receipt->printed_at),
            'reprint_count' => $receipt->reprint_count,
            'created_at' => $this->formatDateTimeOslo($receipt->created_at),
        ];
    }
}
