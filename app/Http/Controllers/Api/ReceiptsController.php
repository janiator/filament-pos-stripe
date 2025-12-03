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

        return response()->json([
            'receipts' => $receipts->getCollection()->map(function ($receipt) {
                return $this->formatReceiptResponse($receipt);
            }),
            'pagination' => [
                'current_page' => $receipts->currentPage(),
                'last_page' => $receipts->lastPage(),
                'per_page' => $receipts->perPage(),
                'total' => $receipts->total(),
            ],
        ]);
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
     * - If receipt is not printed: Returns original receipt XML
     * - If receipt is already printed: Returns copy receipt XML (marked as "KOPI")
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
        if ($receipt->printed) {
            // Check if copy receipt already exists
            $copyReceipt = Receipt::where('store_id', $store->id)
                ->where('receipt_type', 'copy')
                ->where('original_receipt_id', $receipt->id)
                ->first();

            // If no copy receipt exists, create one on the fly
            if (!$copyReceipt) {
                $copyReceipt = $this->createCopyReceipt($receipt);
            }

            $receipt = $copyReceipt;
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

        // If receipt is not printed, mark it as printed (first print)
        // If receipt is already printed, increment reprint count
        if (!$receipt->printed) {
            $receipt->markAsPrinted();
            $message = 'Receipt marked as printed';
        } else {
            $receipt->incrementReprint();
            $message = 'Receipt reprint recorded';
        }

        return response()->json([
            'message' => $message,
            'receipt' => $this->formatReceiptResponse($receipt->load(['posSession', 'charge', 'user'])),
        ]);
    }

    /**
     * Reprint receipt
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

        $receipt->incrementReprint();

        return response()->json([
            'message' => 'Receipt reprinted',
            'receipt' => $this->formatReceiptResponse($receipt->load(['posSession', 'charge', 'user'])),
        ]);
    }

    /**
     * Create a copy receipt from an original receipt
     */
    protected function createCopyReceipt(Receipt $originalReceipt): Receipt
    {
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
     * Format receipt for API response
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
