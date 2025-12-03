<?php

namespace App\Services;

use App\Models\ConnectedCharge;
use App\Models\PaymentMethod;
use App\Models\PosSession;
use App\Models\PosEvent;
use App\Models\Receipt;
use App\Models\Store;
use App\Services\ReceiptGenerationService;
use App\Services\CashDrawerService;
use App\Services\ReceiptPrintService;
use App\Services\SafTCodeMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Throwable;

class PurchaseService
{
    protected ReceiptGenerationService $receiptService;
    protected CashDrawerService $cashDrawerService;
    protected ReceiptPrintService $receiptPrintService;
    protected ?StripeClient $stripeClient = null;

    public function __construct(
        ReceiptGenerationService $receiptService,
        CashDrawerService $cashDrawerService,
        ReceiptPrintService $receiptPrintService
    ) {
        $this->receiptService = $receiptService;
        $this->cashDrawerService = $cashDrawerService;
        $this->receiptPrintService = $receiptPrintService;
    }

    /**
     * Process a purchase with the given payment method
     *
     * @param PosSession $posSession
     * @param PaymentMethod $paymentMethod
     * @param array $cartData
     * @param array $metadata
     * @return array
     * @throws \Exception
     */
    public function processPurchase(
        PosSession $posSession,
        PaymentMethod $paymentMethod,
        array $cartData,
        array $metadata = []
    ): array {
        DB::beginTransaction();

        try {
            // Validate payment method is enabled
            if (!$paymentMethod->enabled) {
                throw new \Exception('Payment method is not enabled');
            }

            // Validate payment method belongs to store
            if ($paymentMethod->store_id !== $posSession->store_id) {
                throw new \Exception('Payment method does not belong to this store');
            }

            // Extract cart totals
            $totalAmount = $cartData['total'] ?? 0; // in øre
            $currency = $cartData['currency'] ?? 'nok';

            // Process payment based on provider
            $charge = match ($paymentMethod->provider) {
                'cash' => $this->processCashPayment($posSession, $paymentMethod, $totalAmount, $currency, $cartData, $metadata),
                'stripe' => $this->processStripePayment($posSession, $paymentMethod, $totalAmount, $currency, $cartData, $metadata),
                default => $this->processOtherPayment($posSession, $paymentMethod, $totalAmount, $currency, $cartData, $metadata),
            };

            // Generate receipt
            $receipt = $this->receiptService->generateSalesReceipt($charge, $posSession);

            // Log POS event (13012 - Sales receipt)
            $posEvent = $this->logSalesReceiptEvent($posSession, $charge, $receipt, $paymentMethod);

            // Open cash drawer for cash payments
            if ($paymentMethod->isCash()) {
                $this->cashDrawerService->openCashDrawer($posSession, $totalAmount);
            }

            // Auto-print receipt (if configured)
            if (config('pos.auto_print_receipts', true)) {
                $this->receiptPrintService->printReceipt($receipt, $posSession);
            }

            // Update POS session totals
            $this->updatePosSessionTotals($posSession, $charge, $paymentMethod);

            DB::commit();

            return [
                'charge' => $charge,
                'receipt' => $receipt,
                'pos_event' => $posEvent,
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Purchase processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'pos_session_id' => $posSession->id,
                'payment_method_id' => $paymentMethod->id,
            ]);
            throw $e;
        }
    }

    /**
     * Process cash payment
     */
    protected function processCashPayment(
        PosSession $posSession,
        PaymentMethod $paymentMethod,
        int $amount,
        string $currency,
        array $cartData,
        array $metadata
    ): ConnectedCharge {
        $store = $posSession->store;

        // Create charge immediately (cash is always successful)
        // Note: stripe_charge_id is null for cash payments since they don't go through Stripe
        $charge = ConnectedCharge::create([
            'stripe_charge_id' => null, // Cash payments don't have a Stripe charge ID
            'stripe_account_id' => $store->stripe_account_id,
            'pos_session_id' => $posSession->id,
            'stripe_customer_id' => $cartData['customer_id'] ?? null,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'succeeded',
            'payment_method' => $paymentMethod->code,
            'payment_code' => $paymentMethod->saf_t_payment_code ?? SafTCodeMapper::mapPaymentMethodToCode($paymentMethod->code, $paymentMethod->provider_method),
            'transaction_code' => SafTCodeMapper::mapTransactionToCodeForPayment($paymentMethod->code),
            'description' => $metadata['description'] ?? 'Cash payment',
            'captured' => true,
            'refunded' => false,
            'paid' => true,
            'paid_at' => now(),
            'metadata' => array_merge([
                'items' => $cartData['items'] ?? [],
                'discounts' => $cartData['discounts'] ?? [],
                'customer_id' => $cartData['customer_id'] ?? null,
                'customer_name' => $cartData['customer_name'] ?? null,
                'tip_amount' => $cartData['tip_amount'] ?? 0,
                'subtotal' => $cartData['subtotal'] ?? 0,
                'total_discounts' => $cartData['total_discounts'] ?? 0,
                'total_tax' => $cartData['total_tax'] ?? 0,
                'total' => $amount,
            ], $metadata),
        ]);

        // Log cash payment event (13016)
        $this->logPaymentEvent($posSession, $charge, $paymentMethod, '13016');

        return $charge;
    }

    /**
     * Process Stripe payment
     * Note: For terminal payments, the payment intent should already be created and confirmed
     * This method creates the charge from a confirmed payment intent
     */
    protected function processStripePayment(
        PosSession $posSession,
        PaymentMethod $paymentMethod,
        int $amount,
        string $currency,
        array $cartData,
        array $metadata
    ): ConnectedCharge {
        $store = $posSession->store;

        // Check if we have a payment intent ID (from terminal or card payment)
        $paymentIntentId = $metadata['payment_intent_id'] ?? null;

        if (!$paymentIntentId) {
            throw new \Exception('Payment intent ID is required for Stripe payments');
        }

        // Retrieve payment intent from Stripe with charges expanded
        $stripe = $this->getStripeClient();
        
        // Retry logic: sometimes charges take a moment to appear after confirmation
        $maxRetries = 3;
        $retryDelay = 1; // seconds
        $paymentIntent = null;
        $stripeChargeId = null;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $paymentIntent = $stripe->paymentIntents->retrieve(
                $paymentIntentId,
                ['expand' => ['charges.data']], // Expand charges to ensure they're loaded
                ['stripe_account' => $store->stripe_account_id]
            );

            if ($paymentIntent->status !== 'succeeded') {
                throw new \Exception('Payment intent is not succeeded');
            }

            // First, try to get charge from latest_charge field (often available immediately)
            if (isset($paymentIntent->latest_charge) && !empty($paymentIntent->latest_charge)) {
                $stripeChargeId = $paymentIntent->latest_charge;
                break; // Found charge, exit retry loop
            }
            
            // Otherwise, check if charges are available in the expanded charges array
            if (isset($paymentIntent->charges) && 
                isset($paymentIntent->charges->data) && 
                count($paymentIntent->charges->data) > 0) {
                $stripeChargeId = $paymentIntent->charges->data[0]->id;
                break; // Found charge, exit retry loop
            }
            
            // If no charge found and not last attempt, wait and retry
            if ($attempt < $maxRetries) {
                sleep($retryDelay);
                continue;
            }
        }

        // Find or create charge from payment intent
        $charge = ConnectedCharge::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if (!$charge) {
            // Get the charge from payment intent (should be available after retry)
            if (!$stripeChargeId) {
                // Last resort: try to get charge directly from payment intent
                // Check latest_charge first (most reliable)
                if (isset($paymentIntent->latest_charge) && !empty($paymentIntent->latest_charge)) {
                    $stripeChargeId = $paymentIntent->latest_charge;
                } elseif (isset($paymentIntent->charges) && 
                          isset($paymentIntent->charges->data) && 
                          count($paymentIntent->charges->data) > 0) {
                    $stripeChargeId = $paymentIntent->charges->data[0]->id;
                }
                
                if (!$stripeChargeId) {
                    Log::error('No charge found in payment intent', [
                        'payment_intent_id' => $paymentIntentId,
                        'payment_intent_status' => $paymentIntent->status ?? 'unknown',
                        'has_latest_charge' => isset($paymentIntent->latest_charge),
                        'latest_charge' => $paymentIntent->latest_charge ?? null,
                        'has_charges' => isset($paymentIntent->charges),
                        'charges_count' => isset($paymentIntent->charges->data) ? count($paymentIntent->charges->data) : 0,
                    ]);
                    throw new \Exception('No charge found in payment intent after retries. Payment intent ID: ' . $paymentIntentId);
                }
            }

            $charge = ConnectedCharge::create([
                'stripe_charge_id' => $stripeChargeId,
                'stripe_account_id' => $store->stripe_account_id,
                'pos_session_id' => $posSession->id,
                'stripe_customer_id' => $paymentIntent->customer ?? $cartData['customer_id'] ?? null,
                'stripe_payment_intent_id' => $paymentIntentId,
                'amount' => $amount, // Use provided amount (may be split payment)
                'currency' => $paymentIntent->currency,
                'status' => 'succeeded',
                'payment_method' => $paymentMethod->code,
                'payment_code' => $paymentMethod->saf_t_payment_code ?? SafTCodeMapper::mapPaymentMethodToCode($paymentMethod->code),
                'transaction_code' => SafTCodeMapper::mapTransactionToCodeForPayment($paymentMethod->code),
                'description' => $paymentIntent->description ?? $metadata['description'] ?? 'Card payment',
                'captured' => $paymentIntent->status === 'succeeded',
                'refunded' => false,
                'paid' => true,
                'paid_at' => now(),
                'metadata' => array_merge([
                    'items' => $cartData['items'] ?? [],
                    'discounts' => $cartData['discounts'] ?? [],
                    'customer_id' => $cartData['customer_id'] ?? null,
                    'customer_name' => $cartData['customer_name'] ?? null,
                    'tip_amount' => $cartData['tip_amount'] ?? 0,
                    'subtotal' => $cartData['subtotal'] ?? 0,
                    'total_discounts' => $cartData['total_discounts'] ?? 0,
                    'total_tax' => $cartData['total_tax'] ?? 0,
                    'total' => $amount,
                ], $metadata),
            ]);
        } else {
            // Update existing charge with cart data
            $charge->update([
                'pos_session_id' => $posSession->id,
                'metadata' => array_merge($charge->metadata ?? [], [
                    'items' => $cartData['items'] ?? [],
                    'discounts' => $cartData['discounts'] ?? [],
                    'customer_id' => $cartData['customer_id'] ?? null,
                    'customer_name' => $cartData['customer_name'] ?? null,
                    'tip_amount' => $cartData['tip_amount'] ?? 0,
                    'subtotal' => $cartData['subtotal'] ?? 0,
                    'total_discounts' => $cartData['total_discounts'] ?? 0,
                    'total_tax' => $cartData['total_tax'] ?? 0,
                    'total' => $amount,
                ]),
            ]);
        }

        // Log card payment event (13017)
        $this->logPaymentEvent($posSession, $charge, $paymentMethod, '13017');

        return $charge;
    }

    /**
     * Process other payment providers
     */
    protected function processOtherPayment(
        PosSession $posSession,
        PaymentMethod $paymentMethod,
        int $amount,
        string $currency,
        array $cartData,
        array $metadata
    ): ConnectedCharge {
        $store = $posSession->store;

        // For other providers, we'll need to implement custom logic
        // For now, create a charge with status pending
        // Note: stripe_charge_id is null for non-Stripe payments
        $charge = ConnectedCharge::create([
            'stripe_charge_id' => null, // Other payment providers don't have Stripe charge ID
            'stripe_account_id' => $store->stripe_account_id,
            'pos_session_id' => $posSession->id,
            'stripe_customer_id' => $cartData['customer_id'] ?? null,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'payment_method' => $paymentMethod->code,
            'payment_code' => $paymentMethod->saf_t_payment_code ?? SafTCodeMapper::mapPaymentMethodToCode($paymentMethod->code, $paymentMethod->provider_method),
            'transaction_code' => SafTCodeMapper::mapTransactionToCodeForPayment($paymentMethod->code),
            'description' => $metadata['description'] ?? 'Other payment',
            'captured' => false,
            'refunded' => false,
            'paid' => false,
            'metadata' => array_merge([
                'items' => $cartData['items'] ?? [],
                'discounts' => $cartData['discounts'] ?? [],
                'customer_id' => $cartData['customer_id'] ?? null,
                'customer_name' => $cartData['customer_name'] ?? null,
                'tip_amount' => $cartData['tip_amount'] ?? 0,
                'subtotal' => $cartData['subtotal'] ?? 0,
                'total_discounts' => $cartData['total_discounts'] ?? 0,
                'total_tax' => $cartData['total_tax'] ?? 0,
                'total' => $amount,
            ], $metadata),
        ]);

        // Log other payment event (13019)
        $this->logPaymentEvent($posSession, $charge, $paymentMethod, '13019');

        return $charge;
    }

    /**
     * Log sales receipt event (13012)
     */
    protected function logSalesReceiptEvent(
        PosSession $posSession,
        ConnectedCharge $charge,
        Receipt $receipt,
        PaymentMethod $paymentMethod
    ): PosEvent {
        return PosEvent::create([
            'store_id' => $posSession->store_id,
            'pos_session_id' => $posSession->id,
            'pos_device_id' => $posSession->pos_device_id,
            'user_id' => $posSession->user_id,
            'related_charge_id' => $charge->id,
            'event_code' => '13012', // Sales receipt
            'event_type' => 'transaction',
            'description' => 'Sales receipt generated',
            'event_data' => [
                'charge_id' => $charge->id,
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'amount' => $charge->amount,
                'payment_method' => $paymentMethod->code,
                'payment_code' => $charge->payment_code,
                'transaction_code' => $charge->transaction_code,
            ],
            'occurred_at' => now(),
        ]);
    }

    /**
     * Log payment event (13016-13019)
     */
    protected function logPaymentEvent(
        PosSession $posSession,
        ConnectedCharge $charge,
        PaymentMethod $paymentMethod,
        string $eventCode
    ): PosEvent {
        return PosEvent::create([
            'store_id' => $posSession->store_id,
            'pos_session_id' => $posSession->id,
            'pos_device_id' => $posSession->pos_device_id,
            'user_id' => $posSession->user_id,
            'related_charge_id' => $charge->id,
            'event_code' => $eventCode,
            'event_type' => 'payment',
            'description' => $this->getPaymentEventDescription($eventCode),
            'event_data' => [
                'charge_id' => $charge->id,
                'amount' => $charge->amount,
                'payment_method' => $paymentMethod->code,
                'payment_code' => $charge->payment_code,
            ],
            'occurred_at' => now(),
        ]);
    }

    /**
     * Get payment event description
     */
    protected function getPaymentEventDescription(string $eventCode): string
    {
        return match($eventCode) {
            '13016' => 'Cash payment',
            '13017' => 'Card payment',
            '13018' => 'Mobile payment',
            '13019' => 'Other payment method',
            default => 'Payment event',
        };
    }

    /**
     * Get Stripe client instance
     */
    protected function getStripeClient(): StripeClient
    {
        if ($this->stripeClient === null) {
            $secret = config('cashier.secret') ?? config('services.stripe.secret');
            if (!$secret) {
                throw new \Exception('Stripe secret key is not configured');
            }
            $this->stripeClient = new StripeClient($secret);
        }

        return $this->stripeClient;
    }

    /**
     * Process a purchase with split payments (multiple payment methods)
     *
     * @param PosSession $posSession
     * @param array $payments Array of payment data: [['payment_method_code' => 'cash', 'amount' => 5000, 'metadata' => []], ...]
     * @param array $cartData
     * @param array $metadata
     * @return array
     * @throws \Exception
     */
    public function processSplitPurchase(
        PosSession $posSession,
        array $payments,
        array $cartData,
        array $metadata = []
    ): array {
        DB::beginTransaction();

        try {
            // Validate payment amounts sum to cart total
            $totalPaid = array_sum(array_column($payments, 'amount'));
            $cartTotal = $cartData['total'] ?? 0;

            if ($totalPaid !== $cartTotal) {
                throw new \Exception("Payment amounts (${totalPaid}) do not match cart total (${cartTotal})");
            }

            if (empty($payments)) {
                throw new \Exception('At least one payment is required');
            }

            $currency = $cartData['currency'] ?? 'nok';
            $charges = [];
            $paymentMethods = [];

            // Process each payment
            foreach ($payments as $paymentData) {
                $paymentMethodCode = $paymentData['payment_method_code'] ?? null;
                $paymentAmount = $paymentData['amount'] ?? 0;

                if (!$paymentMethodCode) {
                    throw new \Exception('Payment method code is required for each payment');
                }

                if ($paymentAmount <= 0) {
                    throw new \Exception('Payment amount must be positive');
                }

                // Get payment method
                $paymentMethod = PaymentMethod::where('store_id', $posSession->store_id)
                    ->where('code', $paymentMethodCode)
                    ->first();

                if (!$paymentMethod) {
                    throw new \Exception("Payment method not found: {$paymentMethodCode}");
                }

                if (!$paymentMethod->enabled) {
                    throw new \Exception("Payment method is not enabled: {$paymentMethodCode}");
                }

                // Process individual payment
                $paymentMetadata = array_merge($metadata, $paymentData['metadata'] ?? []);
                $charge = $this->processPayment(
                    $posSession,
                    $paymentMethod,
                    $paymentAmount,
                    $currency,
                    $cartData,
                    $paymentMetadata
                );

                $charges[] = $charge;
                $paymentMethods[] = $paymentMethod;

                // Open cash drawer for cash payments
                if ($paymentMethod->isCash()) {
                    $this->cashDrawerService->openCashDrawer($posSession, $paymentAmount);
                }
            }

            // Generate single receipt for all payments
            $receipt = $this->receiptService->generateSalesReceipt($charges, $posSession);

            // Log sales receipt event (13012)
            $posEvent = $this->logSplitSalesReceiptEvent($posSession, $charges, $receipt, $paymentMethods);

            // Auto-print receipt (if configured)
            if (config('pos.auto_print_receipts', true)) {
                $this->receiptPrintService->printReceipt($receipt, $posSession);
            }

            // Update POS session totals
            $totalAmount = array_sum(array_column($charges, 'amount'));
            $this->updatePosSessionTotals($posSession, $charges[0], $paymentMethods[0], $totalAmount);

            DB::commit();

            return [
                'charges' => $charges,
                'receipt' => $receipt,
                'pos_event' => $posEvent,
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Split purchase processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'pos_session_id' => $posSession->id,
                'payments' => $payments,
            ]);
            throw $e;
        }
    }

    /**
     * Process a single payment (extracted from processCashPayment, processStripePayment, etc.)
     *
     * @param PosSession $posSession
     * @param PaymentMethod $paymentMethod
     * @param int $amount
     * @param string $currency
     * @param array $cartData
     * @param array $metadata
     * @return ConnectedCharge
     */
    protected function processPayment(
        PosSession $posSession,
        PaymentMethod $paymentMethod,
        int $amount,
        string $currency,
        array $cartData,
        array $metadata
    ): ConnectedCharge {
        return match ($paymentMethod->provider) {
            'cash' => $this->processCashPayment($posSession, $paymentMethod, $amount, $currency, $cartData, $metadata),
            'stripe' => $this->processStripePayment($posSession, $paymentMethod, $amount, $currency, $cartData, $metadata),
            default => $this->processOtherPayment($posSession, $paymentMethod, $amount, $currency, $cartData, $metadata),
        };
    }

    /**
     * Log sales receipt event for split payments
     */
    protected function logSplitSalesReceiptEvent(
        PosSession $posSession,
        array $charges,
        Receipt $receipt,
        array $paymentMethods
    ): PosEvent {
        $primaryCharge = $charges[0];
        $totalAmount = array_sum(array_column($charges, 'amount'));

        return PosEvent::create([
            'store_id' => $posSession->store_id,
            'pos_session_id' => $posSession->id,
            'pos_device_id' => $posSession->pos_device_id,
            'user_id' => $posSession->user_id,
            'related_charge_id' => $primaryCharge->id,
            'event_code' => '13012', // Sales receipt
            'event_type' => 'transaction',
            'description' => 'Sales receipt generated (split payment)',
            'event_data' => [
                'charge_ids' => array_column($charges, 'id'),
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'total_amount' => $totalAmount,
                'payment_count' => count($charges),
                'payments' => array_map(function ($charge, $paymentMethod) {
                    return [
                        'charge_id' => $charge->id,
                        'payment_method' => $paymentMethod->code,
                        'amount' => $charge->amount,
                        'payment_code' => $charge->payment_code,
                    ];
                }, $charges, $paymentMethods),
            ],
            'occurred_at' => now(),
        ]);
    }

    /**
     * Update POS session totals after purchase
     */
    protected function updatePosSessionTotals(
        PosSession $posSession,
        ConnectedCharge $charge,
        PaymentMethod $paymentMethod,
        ?int $totalAmount = null
    ): void {
        $amount = $totalAmount ?? $charge->amount;

        $posSession->increment('transaction_count');
        $posSession->increment('total_amount', $amount);

        // For cash payments, update expected cash
        if ($paymentMethod->isCash()) {
            $posSession->increment('expected_cash', $amount);
        }

        $posSession->save();
    }
}

