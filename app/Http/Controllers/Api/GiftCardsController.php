<?php

namespace App\Http\Controllers\Api;

use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Models\PaymentMethod;
use App\Models\PosSession;
use App\Services\GiftCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GiftCardsController extends BaseApiController
{
    protected GiftCardService $giftCardService;

    public function __construct(GiftCardService $giftCardService)
    {
        $this->giftCardService = $giftCardService;
    }

    /**
     * Purchase a gift card
     */
    public function purchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pos_session_id' => 'required|exists:pos_sessions,id',
            'payment_method_code' => 'required|string',
            'amount' => 'required|numeric|min:1', // Amount in NOK (kroner)
            'currency' => 'sometimes|string|size:3',
            'expires_at' => 'sometimes|nullable|date',
            'customer_id' => 'sometimes|nullable|exists:stripe_connected_customer_mappings,id',
            'customer_name' => 'sometimes|nullable|string',
            'notes' => 'sometimes|nullable|string',
            'pin_required' => 'sometimes|boolean',
            'pin' => 'sometimes|nullable|string|min:4|max:8',
            'code_prefix' => 'sometimes|string|max:10',
            'metadata' => 'sometimes|array',
        ]);

        // Convert kroner to øre
        $validated['amount'] = (int) round($validated['amount'] * 100);

        $posSession = PosSession::findOrFail($validated['pos_session_id']);
        $store = $posSession->store;

        $this->authorizeTenant($request, $store);

        // Get payment method
        $paymentMethod = PaymentMethod::where('store_id', $store->id)
            ->where('code', $validated['payment_method_code'])
            ->where('enabled', true)
            ->firstOrFail();

        // Prepare options
        $options = [
            'currency' => $validated['currency'] ?? 'nok',
            'expires_at' => isset($validated['expires_at']) ? new \DateTime($validated['expires_at']) : null,
            'customer_id' => $validated['customer_id'] ?? null,
            'customer_name' => $validated['customer_name'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'pin_required' => $validated['pin_required'] ?? false,
            'pin' => $validated['pin'] ?? null,
            'code_prefix' => $validated['code_prefix'] ?? 'GC-',
            'metadata' => $validated['metadata'] ?? [],
        ];

        try {
            $giftCard = $this->giftCardService->purchaseGiftCard(
                $posSession,
                $paymentMethod,
                $validated['amount'],
                $options
            );

            // Get the charge and receipt
            $charge = $giftCard->purchaseCharge;
            $receipt = $charge?->receipt;

            return response()->json([
                'gift_card' => [
                    'id' => $giftCard->id,
                    'code' => $giftCard->code,
                    'pin' => $options['pin'] ?? null, // Return PIN only on creation
                    'initial_amount' => $giftCard->initial_amount / 100, // Return in kroner
                    'balance' => $giftCard->balance / 100, // Return in kroner
                    'currency' => $giftCard->currency,
                    'status' => $giftCard->status,
                    'purchased_at' => $this->formatDateTimeOslo($giftCard->purchased_at),
                    'expires_at' => $this->formatDateTimeOslo($giftCard->expires_at),
                ],
                'charge' => $charge ? [
                    'id' => $charge->id,
                    'amount' => $charge->amount / 100, // Return in kroner
                    'status' => $charge->status,
                ] : null,
                'receipt' => $receipt ? [
                    'id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                ] : null,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to purchase gift card',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get gift card by code
     */
    public function show(Request $request, string $code): JsonResponse
    {
        $giftCard = GiftCard::where('code', $code)->first();

        if (!$giftCard) {
            return response()->json(['message' => 'Gift card not found'], 404);
        }

        $store = $giftCard->store;
        $this->authorizeTenant($request, $store);

        return response()->json([
            'id' => $giftCard->id,
            'code' => $giftCard->code,
            'balance' => $giftCard->balance / 100, // Return in kroner
            'currency' => $giftCard->currency,
            'status' => $giftCard->status,
            'purchased_at' => $this->formatDateTimeOslo($giftCard->purchased_at),
            'expires_at' => $this->formatDateTimeOslo($giftCard->expires_at),
            'last_used_at' => $this->formatDateTimeOslo($giftCard->last_used_at),
            'can_redeem' => $giftCard->isValid(),
            'formatted_balance' => $giftCard->formatted_balance,
        ]);
    }

    /**
     * Validate gift card
     */
    public function validateGiftCard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'pin' => 'sometimes|nullable|string',
            'amount' => 'required|numeric|min:0.01', // Amount in NOK (kroner)
        ]);

        // Convert kroner to øre for validation
        $amountInOre = (int) round($validated['amount'] * 100);

        $result = $this->giftCardService->validateGiftCard(
            $validated['code'],
            $amountInOre,
            $validated['pin'] ?? null
        );

        // Convert balance back to kroner in response
        if (isset($result['gift_card']['balance'])) {
            $result['gift_card']['balance'] = $result['gift_card']['balance'] / 100;
        }

        if (!$result['valid']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * List gift cards
     */
    public function index(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $query = GiftCard::where('store_id', $store->id)
            ->with(['purchaseCharge', 'customer', 'purchasedByUser']);

        // Filter by status
        if ($request->has('status') && $request->get('status') !== null) {
            $query->where('status', $request->get('status'));
        }

        // Search by code
        if ($request->has('search') && !empty($request->get('search'))) {
            $search = trim($request->get('search'));
            $query->where('code', 'like', "%{$search}%");
        }

        // Filter by date range
        if ($request->has('date_from') && $request->get('date_from') !== null) {
            $query->whereDate('purchased_at', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to') && $request->get('date_to') !== null) {
            $query->whereDate('purchased_at', '<=', $request->get('date_to'));
        }

        // Pagination
        $perPage = min($request->get('per_page', 50), 100);
        $giftCards = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $giftCards->items()->map(function ($giftCard) {
                return [
                    'id' => $giftCard->id,
                    'code' => $giftCard->code,
                    'initial_amount' => $giftCard->initial_amount / 100, // Return in kroner
                    'balance' => $giftCard->balance / 100, // Return in kroner
                    'status' => $giftCard->status,
                    'purchased_at' => $this->formatDateTimeOslo($giftCard->purchased_at),
                    'expires_at' => $this->formatDateTimeOslo($giftCard->expires_at),
                    'last_used_at' => $this->formatDateTimeOslo($giftCard->last_used_at),
                    'formatted_balance' => $giftCard->formatted_balance,
                ];
            }),
            'total' => $giftCards->total(),
            'per_page' => $giftCards->perPage(),
            'current_page' => $giftCards->currentPage(),
            'last_page' => $giftCards->lastPage(),
        ]);
    }

    /**
     * Get gift card transactions
     */
    public function transactions(Request $request, int $id): JsonResponse
    {
        $giftCard = GiftCard::findOrFail($id);
        $store = $giftCard->store;

        $this->authorizeTenant($request, $store);

        $query = GiftCardTransaction::where('gift_card_id', $id)
            ->with(['charge', 'posSession', 'user']);

        // Filter by type
        if ($request->has('type') && $request->get('type') !== null) {
            $query->where('type', $request->get('type'));
        }

        // Pagination
        $limit = min($request->get('limit', 50), 100);
        $offset = $request->get('offset', 0);

        $transactions = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return response()->json([
            'data' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount / 100, // Return in kroner
                    'balance_before' => $transaction->balance_before / 100, // Return in kroner
                    'balance_after' => $transaction->balance_after / 100, // Return in kroner
                    'formatted_amount' => $transaction->formatted_amount,
                    'created_at' => $this->formatDateTimeOslo($transaction->created_at),
                    'charge' => $transaction->charge ? [
                        'id' => $transaction->charge->id,
                        'receipt_number' => $transaction->charge->receipt?->receipt_number,
                    ] : null,
                    'notes' => $transaction->notes,
                ];
            }),
            'total' => GiftCardTransaction::where('gift_card_id', $id)->count(),
        ]);
    }

    /**
     * Refund a gift card
     */
    public function refund(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'refund_payment_method_code' => 'required|string',
            'pos_session_id' => 'required|exists:pos_sessions,id',
        ]);

        $giftCard = GiftCard::findOrFail($id);
        $store = $giftCard->store;

        $this->authorizeTenant($request, $store);

        $posSession = PosSession::findOrFail($validated['pos_session_id']);

        // Get refund payment method
        $refundPaymentMethod = PaymentMethod::where('store_id', $store->id)
            ->where('code', $validated['refund_payment_method_code'])
            ->where('enabled', true)
            ->firstOrFail();

        try {
            $transaction = $this->giftCardService->refundGiftCard(
                $giftCard,
                $validated['reason'],
                $refundPaymentMethod,
                $posSession
            );

            return response()->json([
                'message' => 'Gift card refunded successfully',
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'created_at' => $this->formatDateTimeOslo($transaction->created_at),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to refund gift card',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Void a gift card
     */
    public function void(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'pos_session_id' => 'required|exists:pos_sessions,id',
        ]);

        $giftCard = GiftCard::findOrFail($id);
        $store = $giftCard->store;

        $this->authorizeTenant($request, $store);

        $posSession = PosSession::findOrFail($validated['pos_session_id']);

        try {
            $this->giftCardService->voidGiftCard(
                $giftCard,
                $validated['reason'],
                $posSession
            );

            return response()->json([
                'message' => 'Gift card voided successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to void gift card',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Adjust gift card balance
     */
    public function adjustBalance(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric', // Amount in NOK (kroner)
            'reason' => 'required|string|max:500',
            'pos_session_id' => 'required|exists:pos_sessions,id',
        ]);

        // Convert kroner to øre
        $validated['amount'] = (int) round($validated['amount'] * 100);

        $giftCard = GiftCard::findOrFail($id);
        $store = $giftCard->store;

        $this->authorizeTenant($request, $store);

        $posSession = PosSession::findOrFail($validated['pos_session_id']);

        try {
            $transaction = $this->giftCardService->adjustBalance(
                $giftCard,
                $validated['amount'],
                $validated['reason'],
                $posSession
            );

            return response()->json([
                'message' => 'Gift card balance adjusted successfully',
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount / 100, // Return in kroner
                    'balance_before' => $transaction->balance_before / 100, // Return in kroner
                    'balance_after' => $transaction->balance_after / 100, // Return in kroner
                    'created_at' => $this->formatDateTimeOslo($transaction->created_at),
                ],
                'gift_card' => [
                    'id' => $giftCard->fresh()->id,
                    'balance' => $giftCard->fresh()->balance / 100, // Return in kroner
                    'formatted_balance' => $giftCard->fresh()->formatted_balance,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to adjust gift card balance',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}



