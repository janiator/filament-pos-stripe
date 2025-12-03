<?php

namespace App\Http\Controllers\Api;

use App\Models\PaymentMethod;
use App\Models\PosSession;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PurchasesController extends BaseApiController
{
    protected PurchaseService $purchaseService;

    public function __construct(PurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
    }

    /**
     * Get available payment methods for a store
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPaymentMethods(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Try to get store from Filament tenant first, then fall back to user's current store
        try {
            $store = \Filament\Facades\Filament::getTenant();
        } catch (\Throwable $e) {
            $store = null;
        }
        
        // If no tenant, try user's current store
        if (!$store) {
            $store = $user->currentStore();
        }

        if (!$store) {
            return response()->json([
                'message' => 'No store found',
            ], 404);
        }

        // Get POS-suitable payment methods by default
        // Can be overridden with ?pos_only=false query parameter
        $posOnly = $request->boolean('pos_only', true);
        
        $query = PaymentMethod::where('store_id', $store->id)
            ->enabled();
            
        if ($posOnly) {
            $query->posSuitable();
        }
        
        $paymentMethods = $query->ordered()->get();

        return response()->json([
            'data' => $paymentMethods,
        ]);
    }

    /**
     * Process a purchase
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Check if this is a split payment request
        $isSplitPayment = $request->has('payments') && is_array($request->input('payments'));

        if ($isSplitPayment) {
            return $this->processSplitPayment($request);
        }

        // Single payment validation
        $validator = Validator::make($request->all(), [
            'pos_session_id' => ['required', 'integer', 'exists:pos_sessions,id'],
            'payment_method_code' => ['required', 'string'],
            'cart' => ['required', 'array'],
            'cart.items' => ['required', 'array', 'min:1'],
            'cart.items.*.product_id' => ['required', 'integer'],
            'cart.items.*.quantity' => ['required', 'integer', 'min:1'],
            'cart.items.*.unit_price' => ['required', 'integer', 'min:0'],
            'cart.total' => ['required', 'integer', 'min:1'],
            'cart.currency' => ['nullable', 'string', 'size:3'],
            'metadata' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Get POS session
        $posSession = PosSession::findOrFail($validated['pos_session_id']);

        // Verify user has access to this session's store
        $user = $request->user();
        
        // Try to get store from Filament tenant first, then fall back to user's current store
        try {
            $userStore = \Filament\Facades\Filament::getTenant();
        } catch (\Throwable $e) {
            $userStore = null;
        }
        
        // If no tenant, try user's current store
        if (!$userStore) {
            $userStore = $user->currentStore();
        }
        
        if (!$userStore || $posSession->store_id !== $userStore->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to POS session',
            ], 403);
        }

        // Verify session is open
        if ($posSession->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'POS session is not open',
            ], 422);
        }

        // Get payment method
        $paymentMethod = PaymentMethod::where('store_id', $posSession->store_id)
            ->where('code', $validated['payment_method_code'])
            ->first();

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found',
            ], 404);
        }

        if (!$paymentMethod->enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method is not enabled',
            ], 422);
        }

        // For Stripe payments, require payment_intent_id in metadata
        if ($paymentMethod->provider === 'stripe') {
            $paymentIntentId = $validated['metadata']['payment_intent_id'] ?? null;
            if (!$paymentIntentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment intent ID is required for Stripe payments',
                ], 422);
            }
        }

        try {
            // Process purchase
            $result = $this->purchaseService->processPurchase(
                $posSession,
                $paymentMethod,
                $validated['cart'],
                $validated['metadata'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'charge' => [
                        'id' => $result['charge']->id,
                        'stripe_charge_id' => $result['charge']->stripe_charge_id,
                        'amount' => $result['charge']->amount,
                        'currency' => $result['charge']->currency,
                        'status' => $result['charge']->status,
                        'payment_method' => $result['charge']->payment_method,
                        'paid_at' => $this->formatDateTimeOslo($result['charge']->paid_at),
                    ],
                    'receipt' => [
                        'id' => $result['receipt']->id,
                        'receipt_number' => $result['receipt']->receipt_number,
                        'receipt_type' => $result['receipt']->receipt_type,
                    ],
                    'pos_event' => [
                        'id' => $result['pos_event']->id,
                        'event_code' => $result['pos_event']->event_code,
                        'transaction_code' => $result['charge']->transaction_code,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process a split payment purchase
     *
     * @param Request $request
     * @return JsonResponse
     */
    protected function processSplitPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pos_session_id' => ['required', 'integer', 'exists:pos_sessions,id'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_code' => ['required', 'string'],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.metadata' => ['nullable', 'array'],
            'cart' => ['required', 'array'],
            'cart.items' => ['required', 'array', 'min:1'],
            'cart.items.*.product_id' => ['required', 'integer'],
            'cart.items.*.quantity' => ['required', 'integer', 'min:1'],
            'cart.items.*.unit_price' => ['required', 'integer', 'min:0'],
            'cart.total' => ['required', 'integer', 'min:1'],
            'cart.currency' => ['nullable', 'string', 'size:3'],
            'metadata' => ['nullable', 'array'],
        ]);

        // Custom validation: payment amounts must sum to cart total
        $validator->after(function ($validator) use ($request) {
            $payments = $request->input('payments', []);
            $cartTotal = $request->input('cart.total', 0);
            $totalPaid = array_sum(array_column($payments, 'amount'));

            if ($totalPaid !== $cartTotal) {
                $validator->errors()->add(
                    'payments',
                    "Payment amounts (${totalPaid}) must equal cart total (${cartTotal})"
                );
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Get POS session
        $posSession = PosSession::findOrFail($validated['pos_session_id']);

        // Verify user has access to this session's store
        $user = $request->user();
        
        try {
            $userStore = \Filament\Facades\Filament::getTenant();
        } catch (\Throwable $e) {
            $userStore = null;
        }
        
        if (!$userStore) {
            $userStore = $user->currentStore();
        }
        
        if (!$userStore || $posSession->store_id !== $userStore->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to POS session',
            ], 403);
        }

        // Verify session is open
        if ($posSession->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'POS session is not open',
            ], 422);
        }

        // Validate all payment methods
        foreach ($validated['payments'] as $index => $paymentData) {
            $paymentMethod = PaymentMethod::where('store_id', $posSession->store_id)
                ->where('code', $paymentData['payment_method_code'])
                ->first();

            if (!$paymentMethod) {
                return response()->json([
                    'success' => false,
                    'message' => "Payment method not found: {$paymentData['payment_method_code']}",
                ], 404);
            }

            if (!$paymentMethod->enabled) {
                return response()->json([
                    'success' => false,
                    'message' => "Payment method is not enabled: {$paymentData['payment_method_code']}",
                ], 422);
            }

            // For Stripe payments, require payment_intent_id
            if ($paymentMethod->provider === 'stripe') {
                $paymentIntentId = $paymentData['metadata']['payment_intent_id'] ?? null;
                if (!$paymentIntentId) {
                    return response()->json([
                        'success' => false,
                        'message' => "Payment intent ID is required for Stripe payment at index {$index}",
                    ], 422);
                }
            }
        }

        try {
            // Process split purchase
            $result = $this->purchaseService->processSplitPurchase(
                $posSession,
                $validated['payments'],
                $validated['cart'],
                $validated['metadata'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'charges' => array_map(function ($charge) {
                        return [
                            'id' => $charge->id,
                            'stripe_charge_id' => $charge->stripe_charge_id,
                            'amount' => $charge->amount,
                            'currency' => $charge->currency,
                            'status' => $charge->status,
                            'payment_method' => $charge->payment_method,
                            'paid_at' => $this->formatDateTimeOslo($charge->paid_at),
                        ];
                    }, $result['charges']),
                    'receipt' => [
                        'id' => $result['receipt']->id,
                        'receipt_number' => $result['receipt']->receipt_number,
                        'receipt_type' => $result['receipt']->receipt_type,
                    ],
                    'pos_event' => [
                        'id' => $result['pos_event']->id,
                        'event_code' => $result['pos_event']->event_code,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
