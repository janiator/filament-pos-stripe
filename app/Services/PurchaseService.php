<?php

namespace App\Services;

use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PaymentMethod;
use App\Models\PosSession;
use App\Models\PosEvent;
use App\Models\ProductVariant;
use App\Models\Receipt;
use App\Models\Store;
use App\Services\ReceiptGenerationService;
use App\Services\CashDrawerService;
use App\Services\ReceiptPrintService;
use App\Services\SafTCodeMapper;
use App\Services\GiftCardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Stripe\StripeClient;
use Throwable;

class PurchaseService
{
    protected ReceiptGenerationService $receiptService;
    protected CashDrawerService $cashDrawerService;
    protected ReceiptPrintService $receiptPrintService;
    protected ?GiftCardService $giftCardService = null;
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
     * Get or create GiftCardService instance (lazy loading to avoid circular dependency)
     */
    protected function getGiftCardService(): GiftCardService
    {
        if (!$this->giftCardService) {
            $this->giftCardService = app(GiftCardService::class);
        }
        return $this->giftCardService;
    }

    /**
     * Resolve customer ID from cart data
     * Accepts customer database ID (integer)
     * Returns Stripe customer ID or null
     *
     * @param array $cartData
     * @param string $stripeAccountId
     * @return string|null
     */
    protected function resolveCustomerId(array $cartData, string $stripeAccountId): ?string
    {
        $customerId = $cartData['customer_id'] ?? null;
        
        // Return null if no customer_id provided
        if ($customerId === null || $customerId === '' || $customerId === 0) {
            return null;
        }
        
        // customer_id is the database ID (integer), look up the customer
        if (is_numeric($customerId)) {
            $customerIdInt = (int) $customerId;
            
            $customer = \App\Models\ConnectedCustomer::where('id', $customerIdInt)
                ->where('stripe_account_id', $stripeAccountId)
                ->first();
            
            if ($customer) {
                if (!$customer->stripe_customer_id) {
                    Log::warning('Customer found but has no stripe_customer_id', [
                        'customer_id' => $customerIdInt,
                        'stripe_account_id' => $stripeAccountId,
                    ]);
                    return null;
                }
                return $customer->stripe_customer_id;
            } else {
                // Log warning if customer_id provided but not found
                Log::warning('Customer ID provided but customer not found', [
                    'customer_id' => $customerIdInt,
                    'stripe_account_id' => $stripeAccountId,
                ]);
            }
        } else {
            // Log warning if customer_id is not numeric
            Log::warning('Invalid customer_id format in cart data', [
                'customer_id' => $customerId,
                'type' => gettype($customerId),
                'stripe_account_id' => $stripeAccountId,
            ]);
        }
        
        // If we can't resolve it, return null
        return null;
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

            // Check if this is a deferred payment (payment on pickup/later)
            $isDeferredPayment = $metadata['deferred_payment'] ?? false;
            $isDeferredPayment = $isDeferredPayment || ($paymentMethod->code === 'deferred' || $paymentMethod->code === 'pay_later');

            // Process payment based on provider
            $charge = match ($paymentMethod->provider) {
                'cash' => $isDeferredPayment 
                    ? $this->processDeferredPayment($posSession, $paymentMethod, $totalAmount, $currency, $cartData, $metadata)
                    : $this->processCashPayment($posSession, $paymentMethod, $totalAmount, $currency, $cartData, $metadata),
                'stripe' => $isDeferredPayment
                    ? $this->processDeferredPayment($posSession, $paymentMethod, $totalAmount, $currency, $cartData, $metadata)
                    : $this->processStripePayment($posSession, $paymentMethod, $totalAmount, $currency, $cartData, $metadata),
                default => $isDeferredPayment
                    ? $this->processDeferredPayment($posSession, $paymentMethod, $totalAmount, $currency, $cartData, $metadata)
                    : $this->processOtherPayment($posSession, $paymentMethod, $totalAmount, $currency, $cartData, $metadata),
            };

            // Generate receipt - use delivery receipt for deferred payments (Kassasystemforskriften § 2-8-7)
            if ($isDeferredPayment) {
                $receipt = $this->receiptService->generateDeliveryReceipt($charge, $posSession);
            } else {
                $receipt = $this->receiptService->generateSalesReceipt($charge, $posSession);
            }

            // Log POS event (13012 - Sales receipt)
            // Note: Delivery receipts for deferred payments still log as sales receipt events
            $posEvent = $this->logSalesReceiptEvent($posSession, $charge, $receipt, $paymentMethod);

            // Don't open cash drawer for deferred payments (payment not received yet)
            if (!$isDeferredPayment && $paymentMethod->isCash()) {
                $this->cashDrawerService->openCashDrawer($posSession, $totalAmount);
            }

            // Auto-print receipt (if configured)
            if (config('pos.auto_print_receipts', true)) {
                $this->receiptPrintService->printReceipt($receipt, $posSession);
            }

            // Update POS session totals (only for paid charges)
            // Deferred payments are not included in totals until paid
            if (!$isDeferredPayment) {
                $this->updatePosSessionTotals($posSession, $charge, $paymentMethod);
            }

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

        // Resolve customer ID (local ID -> Stripe customer ID)
        $stripeCustomerId = $this->resolveCustomerId($cartData, $store->stripe_account_id);
        
        // Create charge immediately (cash is always successful)
        // Note: stripe_charge_id is null for cash payments since they don't go through Stripe
        $charge = ConnectedCharge::create([
            'stripe_charge_id' => null, // Cash payments don't have a Stripe charge ID
            'stripe_account_id' => $store->stripe_account_id,
            'pos_session_id' => $posSession->id,
            'stripe_customer_id' => $stripeCustomerId,
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
                'items' => $this->enrichCartItemsWithProductSnapshots($cartData['items'] ?? [], $store->stripe_account_id),
                'discounts' => $cartData['discounts'] ?? [],
                'customer_id' => $cartData['customer_id'] ?? null,
                'customer_name' => $cartData['customer_name'] ?? null,
                'tip_amount' => $cartData['tip_amount'] ?? 0,
                'subtotal' => $cartData['subtotal'] ?? 0,
                'total_discounts' => $cartData['total_discounts'] ?? 0,
                'total_tax' => $cartData['total_tax'] ?? 0,
                'total' => $amount,
                'note' => $cartData['note'] ?? null,
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

            // Resolve customer ID (local ID -> Stripe customer ID)
            // Prefer cart customer_id if provided, then fall back to payment intent customer
            $stripeCustomerId = $this->resolveCustomerId($cartData, $store->stripe_account_id) ?? $paymentIntent->customer;
            
            $charge = ConnectedCharge::create([
                'stripe_charge_id' => $stripeChargeId,
                'stripe_account_id' => $store->stripe_account_id,
                'pos_session_id' => $posSession->id,
                'stripe_customer_id' => $stripeCustomerId,
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
                    'items' => $this->enrichCartItemsWithProductSnapshots($cartData['items'] ?? [], $store->stripe_account_id),
                    'discounts' => $cartData['discounts'] ?? [],
                    'customer_id' => $cartData['customer_id'] ?? null,
                    'customer_name' => $cartData['customer_name'] ?? null,
                    'tip_amount' => $cartData['tip_amount'] ?? 0,
                    'subtotal' => $cartData['subtotal'] ?? 0,
                    'total_discounts' => $cartData['total_discounts'] ?? 0,
                    'total_tax' => $cartData['total_tax'] ?? 0,
                    'total' => $amount,
                    'note' => $cartData['note'] ?? null,
                ], $metadata),
            ]);
        } else {
            // Update existing charge with cart data
            $charge->update([
                'pos_session_id' => $posSession->id,
                'metadata' => array_merge($charge->metadata ?? [], [
                    'items' => $this->enrichCartItemsWithProductSnapshots($cartData['items'] ?? [], $store->stripe_account_id),
                    'discounts' => $cartData['discounts'] ?? [],
                    'customer_id' => $cartData['customer_id'] ?? null,
                    'customer_name' => $cartData['customer_name'] ?? null,
                    'tip_amount' => $cartData['tip_amount'] ?? 0,
                    'subtotal' => $cartData['subtotal'] ?? 0,
                    'total_discounts' => $cartData['total_discounts'] ?? 0,
                    'total_tax' => $cartData['total_tax'] ?? 0,
                    'total' => $amount,
                    'note' => $cartData['note'] ?? null,
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

        // Handle gift card redemption
        if ($paymentMethod->code === 'gift_token' || $paymentMethod->code === 'gift_card') {
            return $this->processGiftCardPayment($posSession, $paymentMethod, $amount, $currency, $cartData, $metadata);
        }

        // Resolve customer ID (local ID -> Stripe customer ID)
        $stripeCustomerId = $this->resolveCustomerId($cartData, $store->stripe_account_id);

        // For other providers (e.g., Vipps, gift tokens), we assume payment was made
        // and automatically confirm the payment status
        // Note: stripe_charge_id is null for non-Stripe payments
        $eventCode = $paymentMethod->saf_t_event_code ?? SafTCodeMapper::mapPaymentMethodToEventCode($paymentMethod->code, $paymentMethod->provider_method);
        
        $charge = ConnectedCharge::create([
            'stripe_charge_id' => null, // Other payment providers don't have Stripe charge ID
            'stripe_account_id' => $store->stripe_account_id,
            'pos_session_id' => $posSession->id,
            'stripe_customer_id' => $stripeCustomerId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'succeeded',
            'payment_method' => $paymentMethod->code,
            'payment_code' => $paymentMethod->saf_t_payment_code ?? SafTCodeMapper::mapPaymentMethodToCode($paymentMethod->code, $paymentMethod->provider_method),
            'transaction_code' => SafTCodeMapper::mapTransactionToCodeForPayment($paymentMethod->code),
            'description' => $metadata['description'] ?? 'Other payment',
            'captured' => true,
            'refunded' => false,
            'paid' => true,
            'paid_at' => now(),
            'metadata' => array_merge([
                'items' => $this->enrichCartItemsWithProductSnapshots($cartData['items'] ?? [], $store->stripe_account_id),
                'discounts' => $cartData['discounts'] ?? [],
                'customer_id' => $cartData['customer_id'] ?? null,
                'customer_name' => $cartData['customer_name'] ?? null,
                'tip_amount' => $cartData['tip_amount'] ?? 0,
                'subtotal' => $cartData['subtotal'] ?? 0,
                'total_discounts' => $cartData['total_discounts'] ?? 0,
                'total_tax' => $cartData['total_tax'] ?? 0,
                'total' => $amount,
                'note' => $cartData['note'] ?? null,
            ], $metadata),
        ]);

        // Log payment event using the payment method's event code
        $this->logPaymentEvent($posSession, $charge, $paymentMethod, $eventCode);

        return $charge;
    }

    /**
     * Process gift card payment (redemption)
     */
    protected function processGiftCardPayment(
        PosSession $posSession,
        PaymentMethod $paymentMethod,
        int $amount,
        string $currency,
        array $cartData,
        array $metadata
    ): ConnectedCharge {
        $store = $posSession->store;

        // Get gift card code from metadata
        $giftCardCode = $metadata['gift_card_code'] ?? null;
        $giftCardPin = $metadata['gift_card_pin'] ?? null;

        if (!$giftCardCode) {
            throw new \Exception('Gift card code is required for gift card payment');
        }

        // Resolve customer ID
        $stripeCustomerId = $this->resolveCustomerId($cartData, $store->stripe_account_id);

        // Create charge first (before redeeming gift card to ensure we have charge ID)
        $charge = ConnectedCharge::create([
            'stripe_charge_id' => null,
            'stripe_account_id' => $store->stripe_account_id,
            'pos_session_id' => $posSession->id,
            'stripe_customer_id' => $stripeCustomerId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'succeeded',
            'payment_method' => $paymentMethod->code,
            'payment_code' => $paymentMethod->saf_t_payment_code ?? SafTCodeMapper::mapPaymentMethodToCode($paymentMethod->code, $paymentMethod->provider_method),
            'transaction_code' => SafTCodeMapper::mapTransactionToCodeForPayment($paymentMethod->code),
            'description' => $metadata['description'] ?? 'Gift card payment',
            'captured' => true,
            'refunded' => false,
            'paid' => true,
            'paid_at' => now(),
            'metadata' => array_merge([
                'items' => $this->enrichCartItemsWithProductSnapshots($cartData['items'] ?? [], $store->stripe_account_id),
                'discounts' => $cartData['discounts'] ?? [],
                'customer_id' => $cartData['customer_id'] ?? null,
                'customer_name' => $cartData['customer_name'] ?? null,
                'tip_amount' => $cartData['tip_amount'] ?? 0,
                'subtotal' => $cartData['subtotal'] ?? 0,
                'total_discounts' => $cartData['total_discounts'] ?? 0,
                'total_tax' => $cartData['total_tax'] ?? 0,
                'total' => $amount,
                'note' => $cartData['note'] ?? null,
                'gift_card_code' => $giftCardCode,
            ], $metadata),
        ]);

        // Redeem gift card (this will create transaction and log POS event)
        try {
            $giftCardService = $this->getGiftCardService();
            $giftCardService->redeemGiftCard($giftCardCode, $amount, $charge, $posSession, $giftCardPin);
        } catch (\Exception $e) {
            // If redemption fails, delete the charge and rethrow
            $charge->delete();
            throw $e;
        }

        // Log payment event (13019 - Other payment, which includes gift cards)
        $this->logPaymentEvent($posSession, $charge, $paymentMethod, '13019');

        return $charge;
    }

    /**
     * Process deferred payment (payment on pickup/later)
     * Creates a charge with pending status and generates a delivery receipt
     * Complies with Kassasystemforskriften § 2-8-7 (Utleveringskvittering)
     *
     * @param PosSession $posSession
     * @param PaymentMethod $paymentMethod
     * @param int $amount
     * @param string $currency
     * @param array $cartData
     * @param array $metadata
     * @return ConnectedCharge
     */
    protected function processDeferredPayment(
        PosSession $posSession,
        PaymentMethod $paymentMethod,
        int $amount,
        string $currency,
        array $cartData,
        array $metadata
    ): ConnectedCharge {
        $store = $posSession->store;

        // Resolve customer ID (local ID -> Stripe customer ID)
        $stripeCustomerId = $this->resolveCustomerId($cartData, $store->stripe_account_id);

        // Create charge with pending status (not paid yet)
        $charge = ConnectedCharge::create([
            'stripe_charge_id' => null, // Deferred payments don't have Stripe charge ID until paid
            'stripe_account_id' => $store->stripe_account_id,
            'pos_session_id' => $posSession->id,
            'stripe_customer_id' => $stripeCustomerId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'payment_method' => $paymentMethod->code,
            'payment_code' => $paymentMethod->saf_t_payment_code ?? SafTCodeMapper::mapPaymentMethodToCode($paymentMethod->code, $paymentMethod->provider_method),
            'transaction_code' => SafTCodeMapper::mapTransactionToCodeForPayment($paymentMethod->code),
            'description' => $metadata['description'] ?? 'Deferred payment',
            'captured' => false,
            'refunded' => false,
            'paid' => false,
            'paid_at' => null, // Will be set when payment is completed
            'metadata' => array_merge([
                'items' => $this->enrichCartItemsWithProductSnapshots($cartData['items'] ?? [], $store->stripe_account_id),
                'discounts' => $cartData['discounts'] ?? [],
                'customer_id' => $cartData['customer_id'] ?? null,
                'customer_name' => $cartData['customer_name'] ?? null,
                'tip_amount' => $cartData['tip_amount'] ?? 0,
                'subtotal' => $cartData['subtotal'] ?? 0,
                'total_discounts' => $cartData['total_discounts'] ?? 0,
                'total_tax' => $cartData['total_tax'] ?? 0,
                'total' => $amount,
                'note' => $cartData['note'] ?? null,
                'deferred_payment' => true,
                'deferred_reason' => $metadata['deferred_reason'] ?? 'Payment on pickup',
            ], $metadata),
        ]);

        // Log deferred payment event (13019 - Other payment, pending)
        $this->logPaymentEvent($posSession, $charge, $paymentMethod, '13019');

        return $charge;
    }

    /**
     * Complete a deferred payment
     * Updates the charge status and generates a sales receipt
     *
     * @param ConnectedCharge $charge
     * @param PaymentMethod $paymentMethod
     * @param array $paymentData Additional payment data (e.g., payment_intent_id for Stripe)
     * @param PosSession|null $posSession Optional POS session to use. If not provided, uses the charge's original session.
     *                                    This allows completing deferred payments on different devices/sessions.
     * @return array
     * @throws \Exception
     */
    public function completeDeferredPayment(
        ConnectedCharge $charge,
        PaymentMethod $paymentMethod,
        array $paymentData = [],
        ?PosSession $posSession = null
    ): array {
        DB::beginTransaction();

        try {
            // Validate charge is pending
            if ($charge->status !== 'pending' || $charge->paid) {
                throw new \Exception('Charge is not pending or already paid');
            }

            // Validate payment method belongs to store
            $store = $charge->store;
            if ($paymentMethod->store_id !== $store->id) {
                throw new \Exception('Payment method does not belong to this store');
            }

            // Use provided session or fall back to charge's original session
            if (!$posSession) {
                $posSession = $charge->posSession;
            }

            // Validate POS session exists, is open, and belongs to the same store
            if (!$posSession) {
                throw new \Exception('POS session not found');
            }

            if ($posSession->status !== 'open') {
                throw new \Exception('POS session is not open');
            }

            if ($posSession->store_id !== $store->id) {
                throw new \Exception('POS session does not belong to the same store as the charge');
            }

            // If using a different session than the original, update the charge's pos_session_id
            // This ensures proper tracking and that session totals are updated correctly
            if ($charge->pos_session_id !== $posSession->id) {
                $charge->pos_session_id = $posSession->id;
                $charge->save();
            }

            // Process payment based on provider
            if ($paymentMethod->provider === 'stripe') {
                // Handle Stripe payment
                $paymentIntentId = $paymentData['payment_intent_id'] ?? null;
                if (!$paymentIntentId) {
                    throw new \Exception('Payment intent ID is required for Stripe payments');
                }

                $stripe = $this->getStripeClient();
                $paymentIntent = $stripe->paymentIntents->retrieve(
                    $paymentIntentId,
                    ['expand' => ['charges.data']],
                    ['stripe_account' => $store->stripe_account_id]
                );

                if ($paymentIntent->status !== 'succeeded') {
                    throw new \Exception('Payment intent is not succeeded');
                }

                $stripeChargeId = $paymentIntent->latest_charge ?? null;
                if (!$stripeChargeId && isset($paymentIntent->charges->data[0])) {
                    $stripeChargeId = $paymentIntent->charges->data[0]->id;
                }

                if (!$stripeChargeId) {
                    throw new \Exception('Stripe charge ID not found');
                }

                // Update charge with payment information
                $charge->update([
                    'stripe_charge_id' => $stripeChargeId,
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'stripe_customer_id' => $paymentIntent->customer ?? $charge->stripe_customer_id,
                    'status' => 'succeeded',
                    'payment_method' => $paymentMethod->code,
                    'payment_code' => $paymentMethod->saf_t_payment_code ?? SafTCodeMapper::mapPaymentMethodToCode($paymentMethod->code),
                    'transaction_code' => SafTCodeMapper::mapTransactionToCodeForPayment($paymentMethod->code),
                    'captured' => true,
                    'paid' => true,
                    'paid_at' => now(),
                ]);

                // Log card payment event (13017)
                $this->logPaymentEvent($posSession, $charge, $paymentMethod, '13017');
            } elseif ($paymentMethod->isCash()) {
                // Handle cash payment
                $charge->update([
                    'status' => 'succeeded',
                    'payment_method' => $paymentMethod->code,
                    'payment_code' => $paymentMethod->saf_t_payment_code ?? SafTCodeMapper::mapPaymentMethodToCode($paymentMethod->code, $paymentMethod->provider_method),
                    'transaction_code' => SafTCodeMapper::mapTransactionToCodeForPayment($paymentMethod->code),
                    'captured' => true,
                    'paid' => true,
                    'paid_at' => now(),
                ]);

                // Log cash payment event (13016)
                $this->logPaymentEvent($posSession, $charge, $paymentMethod, '13016');

                // Open cash drawer
                $this->cashDrawerService->openCashDrawer($posSession, $charge->amount);
            } elseif ($paymentMethod->provider === 'other') {
                // Handle other payment methods (e.g., Vipps, gift tokens, etc.)
                // These are assumed to be confirmed automatically when completing the payment
                $eventCode = $paymentMethod->saf_t_event_code ?? SafTCodeMapper::mapPaymentMethodToEventCode($paymentMethod->code, $paymentMethod->provider_method);
                
                $charge->update([
                    'status' => 'succeeded',
                    'payment_method' => $paymentMethod->code,
                    'payment_code' => $paymentMethod->saf_t_payment_code ?? SafTCodeMapper::mapPaymentMethodToCode($paymentMethod->code, $paymentMethod->provider_method),
                    'transaction_code' => SafTCodeMapper::mapTransactionToCodeForPayment($paymentMethod->code),
                    'captured' => true,
                    'paid' => true,
                    'paid_at' => now(),
                ]);

                // Log payment event using the payment method's event code
                $this->logPaymentEvent($posSession, $charge, $paymentMethod, $eventCode);
            } else {
                throw new \Exception('Unsupported payment method provider for completing deferred payment');
            }

            // Generate sales receipt for completed payment
            $receipt = $this->receiptService->generateSalesReceipt($charge, $posSession);

            // Log sales receipt event (13012)
            $posEvent = $this->logSalesReceiptEvent($posSession, $charge, $receipt, $paymentMethod);

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
            Log::error('Deferred payment completion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'charge_id' => $charge->id,
                'payment_method_id' => $paymentMethod->id,
            ]);
            throw $e;
        }
    }

    /**
     * Enrich cart items with product snapshots at purchase time
     * This preserves historical product information even if products are later changed or deleted
     *
     * @param array $items
     * @param string $stripeAccountId
     * @return array
     */
    protected function enrichCartItemsWithProductSnapshots(array $items, string $stripeAccountId): array
    {
        if (empty($items)) {
            return [];
        }

        // Collect all product IDs and variant IDs
        $productIds = [];
        $variantIds = [];
        
        foreach ($items as $item) {
            if (isset($item['product_id'])) {
                $productIds[] = (int) $item['product_id'];
            }
            if (isset($item['variant_id'])) {
                $variantIds[] = (int) $item['variant_id'];
            }
        }

        // Fetch products and variants in bulk
        $products = ConnectedProduct::whereIn('id', array_unique($productIds))
            ->where('stripe_account_id', $stripeAccountId)
            ->get()
            ->keyBy('id');

        $variants = ProductVariant::whereIn('id', array_unique($variantIds))
            ->where('stripe_account_id', $stripeAccountId)
            ->get()
            ->keyBy('id');

        // Enrich each item with product snapshot
        return array_map(function ($item) use ($products, $variants) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
            $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : null;
            
            $product = $productId ? ($products[$productId] ?? null) : null;
            $variant = $variantId ? ($variants[$variantId] ?? null) : null;

            // Get product name (from variant if available, otherwise product)
            $productName = null;
            if ($variant && $variant->product) {
                $productName = $variant->product->name;
                if ($variant->variant_name !== 'Default') {
                    $productName .= ' - ' . $variant->variant_name;
                }
            } elseif ($product) {
                $productName = $product->name;
            }

            // Get product image URL (prefer variant image, then product image)
            $productImageUrl = null;
            if ($variant && $variant->image_url) {
                $productImageUrl = $variant->image_url;
            } elseif ($product) {
                // Get first image from product
                if ($product->hasMedia('images')) {
                    $firstMedia = $product->getMedia('images')->first();
                    if ($firstMedia) {
                        // Generate signed URL that expires in 1 year (for historical data)
                        $productImageUrl = URL::temporarySignedRoute(
                            'api.products.images.serve',
                            now()->addYear(),
                            [
                                'product' => $product->id,
                                'media' => $firstMedia->id,
                            ]
                        );
                    }
                } elseif ($product->images && is_array($product->images) && !empty($product->images)) {
                    $productImageUrl = $product->images[0];
                }
            }

            // Get article group code and product code
            $articleGroupCode = null;
            $productCode = null;
            if ($variant && $variant->product) {
                $articleGroupCode = $variant->product->article_group_code;
                $productCode = $variant->product->product_code;
            } elseif ($product) {
                $articleGroupCode = $product->article_group_code;
                $productCode = $product->product_code;
            }

            // Calculate original price (unit_price + discount_amount if discount exists)
            $unitPrice = isset($item['unit_price']) ? (int) $item['unit_price'] : 0;
            $discountAmount = isset($item['discount_amount']) ? (int) $item['discount_amount'] : 0;
            $originalPrice = $discountAmount > 0 ? ($unitPrice + $discountAmount) : null;

            // For diverse products or products without price, use custom description if provided
            // Otherwise use product name. Store both for flexibility.
            // Preserve description from original item (may come from cart)
            $customDescription = $item['description'] ?? null;
            // If description is empty string, treat as null
            if ($customDescription === '') {
                $customDescription = null;
            }
            $itemName = $customDescription ?? $productName;

            // Merge snapshot data with existing item data
            // array_merge preserves all original item fields (including description if present)
            $enrichedItem = array_merge($item, [
                // Store snapshot of product information at purchase time
                'name' => $itemName, // Primary name for receipts (custom description or product name)
                'description' => $customDescription, // Custom description if provided (for diverse products) - explicitly set
                'product_name' => $productName, // Original product name (for reference)
                'product_image_url' => $productImageUrl,
                'original_price' => $originalPrice,
                'article_group_code' => $articleGroupCode,
                'product_code' => $productCode,
            ]);
            
            return $enrichedItem;
        }, $items);
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
     * Cancel a Stripe payment intent
     * 
     * @param string $paymentIntentId
     * @param string $stripeAccountId Connected account ID
     * @return array ['cancelled' => bool, 'error' => string|null]
     */
    public function cancelPaymentIntent(string $paymentIntentId, string $stripeAccountId): array
    {
        try {
            $stripe = $this->getStripeClient();
            $paymentIntent = $stripe->paymentIntents->cancel(
                $paymentIntentId,
                [],
                ['stripe_account' => $stripeAccountId]
            );
            
            return [
                'cancelled' => true,
                'error' => null,
                'payment_intent' => $paymentIntent,
            ];
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Payment intent might already be cancelled, succeeded, or not found
            // This is not necessarily an error - it might already be in the desired state
            return [
                'cancelled' => false,
                'error' => $e->getMessage(),
                'payment_intent' => null,
            ];
        } catch (\Exception $e) {
            // Other errors
            Log::error('Error cancelling Stripe payment intent', [
                'payment_intent_id' => $paymentIntentId,
                'stripe_account_id' => $stripeAccountId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'cancelled' => false,
                'error' => $e->getMessage(),
                'payment_intent' => null,
            ];
        }
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

    /**
     * Process a refund for a purchase
     * 
     * Handles both Stripe refunds and cash refunds (manual process)
     * Generates return receipt and logs POS event (13013)
     * Updates POS session totals
     * 
     * @param ConnectedCharge $charge
     * @param int|null $amount Amount to refund in minor units (øre). If null, refunds full amount.
     * @param string|null $reason Optional reason for refund
     * @param int|null $userId User ID performing the refund
     * @return array ['charge' => ConnectedCharge, 'receipt' => Receipt, 'pos_event' => PosEvent]
     * @throws \Exception
     */
    public function processRefund(
        ConnectedCharge $charge,
        ?int $amount = null,
        ?string $reason = null,
        ?int $userId = null,
        ?array $refundedItems = null,
        ?PosSession $currentPosSession = null,
        ?PosSession $originalPosSession = null
    ): array {
        DB::beginTransaction();

        try {
            // Validate charge can be refunded
            if ($charge->status === 'cancelled') {
                throw new \Exception('Cannot refund a cancelled purchase');
            }

            if ($charge->status === 'pending' || !$charge->paid) {
                throw new \Exception('Cannot refund a purchase that has not been paid');
            }

            // Determine refund amount
            $refundAmount = $amount ?? ($charge->amount - $charge->amount_refunded);
            
            if ($refundAmount <= 0) {
                throw new \Exception('Refund amount must be greater than zero');
            }

            $remainingRefundable = $charge->amount - $charge->amount_refunded;
            if ($refundAmount > $remainingRefundable) {
                throw new \Exception("Refund amount ({$refundAmount}) exceeds remaining refundable amount ({$remainingRefundable})");
            }

            // Determine which POS session to use
            // For compliance: If original session is closed, use current open session
            // Original session totals should not be modified
            if ($currentPosSession !== null) {
                $posSession = $currentPosSession;
                $originalPosSession = $originalPosSession ?? $charge->posSession;
            } else {
                // Fallback: use original session (for backward compatibility)
                $posSession = $charge->posSession;
                $originalPosSession = $charge->posSession;
            }
            
            if (!$posSession) {
                throw new \Exception('POS session not found for charge');
            }
            
            // Ensure current session is open (for compliance)
            if ($posSession->status !== 'open') {
                throw new \Exception('Current POS session must be open for refunds. Closed sessions cannot be modified.');
            }

            // Get payment method
            $paymentMethod = PaymentMethod::where('store_id', $posSession->store_id)
                ->where('code', $charge->payment_method)
                ->first();

            if (!$paymentMethod) {
                throw new \Exception('Payment method not found');
            }

            // Process refund based on payment provider
            $stripeRefundId = null;
            $stripeRefundError = null;
            $refundProcessedAutomatically = false;
            $requiresManualProcessing = false;
            $manualProcessingMessage = null;
            
            if ($charge->stripe_charge_id && $paymentMethod->provider === 'stripe') {
                // Stripe payments can be refunded automatically
                $refundResult = $this->processStripeRefund(
                    $charge->stripe_charge_id,
                    $charge->stripe_account_id,
                    $refundAmount,
                    $reason
                );
                
                $stripeRefundId = $refundResult['refund_id'];
                $stripeRefundError = $refundResult['error'];
                
                if ($stripeRefundError && !$stripeRefundId) {
                    // Stripe refund failed - abort transaction
                    throw new \Exception("Stripe refund failed: {$stripeRefundError}");
                }
                
                $refundProcessedAutomatically = true;
            } else {
                // For non-Stripe payments (cash, Vipps, gift tokens, etc.), refund must be processed manually
                $requiresManualProcessing = true;
                $paymentMethodName = $paymentMethod->name ?? $paymentMethod->code ?? 'payment method';
                $manualProcessingMessage = "Refund for {$paymentMethodName} must be processed manually. Please process the refund through the payment provider's system.";
            }

            // Calculate new refunded amounts
            $newAmountRefunded = $charge->amount_refunded + $refundAmount;
            $isFullyRefunded = $newAmountRefunded >= $charge->amount;

            // Update charge
            $metadata = $charge->metadata ?? [];
            $refunds = $metadata['refunds'] ?? [];
            $refundData = [
                'amount' => $refundAmount,
                'amount_refunded' => $newAmountRefunded,
                'reason' => $reason,
                'refunded_at' => now()->setTimezone('Europe/Oslo')->format('Y-m-d H:i:s'),
                'refunded_by' => $userId,
                'stripe_refund_id' => $stripeRefundId,
                'refund_processed_automatically' => $refundProcessedAutomatically,
                'requires_manual_processing' => $requiresManualProcessing,
                'manual_processing_message' => $manualProcessingMessage,
            ];
            
            // Track refunded items if provided
            if ($refundedItems !== null && !empty($refundedItems)) {
                $refundData['items'] = $refundedItems;
                
                // Update item-level refund tracking in metadata
                $itemRefunds = $metadata['item_refunds'] ?? [];
                foreach ($refundedItems as $refundedItem) {
                    $itemId = $refundedItem['item_id'] ?? null;
                    if ($itemId) {
                        if (!isset($itemRefunds[$itemId])) {
                            $itemRefunds[$itemId] = 0;
                        }
                        $itemRefunds[$itemId] += $refundedItem['quantity'] ?? 1;
                    }
                }
                $metadata['item_refunds'] = $itemRefunds;
            }
            
            $refunds[] = $refundData;
            $metadata['refunds'] = $refunds;
            $metadata['last_refund_at'] = now()->setTimezone('Europe/Oslo')->format('Y-m-d H:i:s');
            $metadata['last_refund_by'] = $userId;
            if ($reason) {
                $metadata['refund_reason'] = $reason;
            }

            $charge->update([
                'amount_refunded' => $newAmountRefunded,
                'refunded' => $isFullyRefunded,
                'status' => $isFullyRefunded ? 'refunded' : $charge->status,
                'metadata' => $metadata,
            ]);

            // Refresh charge to get updated values
            $charge->refresh();

            // Get original receipt (sales receipt for completed purchases, delivery receipt for deferred)
            $originalReceipt = $charge->receipt;
            if (!$originalReceipt) {
                // Try to find any receipt for this charge
                $originalReceipt = Receipt::where('charge_id', $charge->id)
                    ->orderByDesc('created_at')
                    ->first();
            }

            if (!$originalReceipt) {
                throw new \Exception('Original receipt not found for charge');
            }

            // Generate return receipt (pass current refund amount for this specific refund)
            $returnReceipt = $this->receiptService->generateReturnReceipt($charge, $originalReceipt, $refundAmount);

            // Log return receipt event (13013) in current session
            // Store reference to original session for audit trail
            $posEvent = PosEvent::create([
                'store_id' => $posSession->store_id,
                'pos_device_id' => $posSession->pos_device_id,
                'pos_session_id' => $posSession->id, // Current open session
                'user_id' => $userId ?? $posSession->user_id,
                'related_charge_id' => $charge->id,
                'event_code' => PosEvent::EVENT_RETURN_RECEIPT,
                'event_type' => 'transaction',
                'description' => "Return receipt for charge " . ($charge->stripe_charge_id ?? $charge->id),
                'event_data' => [
                    'charge_id' => $charge->id,
                    'stripe_charge_id' => $charge->stripe_charge_id,
                    'stripe_refund_id' => $stripeRefundId,
                    'refund_amount' => $refundAmount,
                    'amount_refunded' => $newAmountRefunded,
                    'original_amount' => $charge->amount,
                    'is_full_refund' => $isFullyRefunded,
                    'reason' => $reason,
                    'receipt_id' => $returnReceipt->id,
                    'receipt_number' => $returnReceipt->receipt_number,
                    'original_receipt_id' => $originalReceipt->id,
                    'original_receipt_number' => $originalReceipt->receipt_number,
                    'original_pos_session_id' => $originalPosSession->id, // Reference to original session
                    'original_pos_session_number' => $originalPosSession->session_number,
                    'refund_in_current_session' => $posSession->id !== $originalPosSession->id, // True if refunding from closed session
                ],
                'occurred_at' => now(),
            ]);

            // Update POS session totals (decrement)
            $this->updatePosSessionTotalsForRefund($posSession, $charge, $paymentMethod, $refundAmount);

            // Auto-print receipt (if configured)
            if (config('pos.auto_print_receipts', true)) {
                $this->receiptPrintService->printReceipt($returnReceipt, $posSession);
            }

            DB::commit();

            return [
                'charge' => $charge->fresh(),
                'receipt' => $returnReceipt,
                'pos_event' => $posEvent,
                'refund_processed_automatically' => $refundProcessedAutomatically,
                'requires_manual_processing' => $requiresManualProcessing,
                'manual_processing_message' => $manualProcessingMessage,
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Refund processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'charge_id' => $charge->id,
                'refund_amount' => $amount,
            ]);
            throw $e;
        }
    }

    /**
     * Process a Stripe refund
     * 
     * @param string $stripeChargeId
     * @param string $stripeAccountId Connected account ID
     * @param int $amount Amount to refund in minor units (øre)
     * @param string|null $reason Optional reason for refund
     * @return array ['refund_id' => string|null, 'error' => string|null]
     */
    protected function processStripeRefund(
        string $stripeChargeId,
        string $stripeAccountId,
        int $amount,
        ?string $reason = null
    ): array {
        try {
            $stripe = $this->getStripeClient();
            
            $params = [
                'charge' => $stripeChargeId,
                'amount' => $amount,
            ];
            
            if ($reason) {
                $params['reason'] = 'requested_by_customer'; // Stripe reason
                $params['metadata'] = [
                    'refund_reason' => $reason,
                ];
            }

            $refund = $stripe->refunds->create(
                $params,
                [
                    'stripe_account' => $stripeAccountId,
                ]
            );

            return [
                'refund_id' => $refund->id,
                'error' => null,
                'refund' => $refund,
            ];
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Log::error('Stripe refund failed', [
                'charge_id' => $stripeChargeId,
                'stripe_account_id' => $stripeAccountId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'refund_id' => null,
                'error' => $e->getMessage(),
                'refund' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Error processing Stripe refund', [
                'charge_id' => $stripeChargeId,
                'stripe_account_id' => $stripeAccountId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'refund_id' => null,
                'error' => $e->getMessage(),
                'refund' => null,
            ];
        }
    }

    /**
     * Update POS session totals after refund
     * 
     * For compliance: Only updates the current open session totals.
     * Closed sessions should not be modified per Kassasystemforskriften.
     * 
     * Decrements total amount and expected cash (for cash payments) in the current session.
     */
    protected function updatePosSessionTotalsForRefund(
        PosSession $posSession,
        ConnectedCharge $charge,
        PaymentMethod $paymentMethod,
        int $refundAmount
    ): void {
        // Only update if session is open (compliance: closed sessions should not be modified)
        if ($posSession->status !== 'open') {
            \Log::warning('Attempted to update totals for closed POS session - skipped for compliance', [
                'pos_session_id' => $posSession->id,
                'pos_session_status' => $posSession->status,
                'charge_id' => $charge->id,
                'refund_amount' => $refundAmount,
            ]);
            return;
        }

        // Decrement total amount in current session
        $posSession->decrement('total_amount', $refundAmount);

        // For cash payments, decrement expected cash in current session
        if ($paymentMethod->isCash()) {
            $posSession->decrement('expected_cash', $refundAmount);
        }

        // Note: We don't decrement transaction_count because the original transaction still exists
        // The refund is tracked as a separate event (return receipt) in the current session

        $posSession->save();
    }
}

