<?php

namespace App\Services;

use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Models\ConnectedCharge;
use App\Models\PosSession;
use App\Models\PosEvent;
use App\Models\PaymentMethod;
use App\Models\Store;
use App\Models\User;
use App\Services\SafTCodeMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GiftCardService
{
    protected ReceiptGenerationService $receiptService;
    protected ?PurchaseService $purchaseService = null;

    public function __construct(
        ReceiptGenerationService $receiptService
    ) {
        $this->receiptService = $receiptService;
    }

    /**
     * Get PurchaseService instance (lazy loading to avoid circular dependency)
     */
    protected function getPurchaseService(): PurchaseService
    {
        if (!$this->purchaseService) {
            $this->purchaseService = app(PurchaseService::class);
        }
        return $this->purchaseService;
    }

    /**
     * Purchase a gift card
     */
    public function purchaseGiftCard(
        PosSession $posSession,
        PaymentMethod $paymentMethod,
        int $amount,
        array $options = []
    ): GiftCard {
        DB::beginTransaction();

        try {
            // Validate amount
            $minAmount = $options['min_amount'] ?? 10000; // 100 NOK default
            $maxAmount = $options['max_amount'] ?? 1000000; // 10000 NOK default

            if ($amount < $minAmount) {
                throw new \Exception("Gift card amount must be at least " . ($minAmount / 100) . " NOK");
            }

            if ($amount > $maxAmount) {
                throw new \Exception("Gift card amount cannot exceed " . ($maxAmount / 100) . " NOK");
            }

            // Generate unique code
            $code = $options['code'] ?? GiftCard::generateCode($options['code_prefix'] ?? 'GC-');
            
            // Generate PIN if required
            $pin = null;
            if ($options['pin_required'] ?? false) {
                $pin = $options['pin'] ?? str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            }

            // Create cart data for gift card purchase
            $cartData = [
                'items' => [[
                    'product_id' => null,
                    'product_name' => 'Gavekort',
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'article_group_code' => $options['article_group_code'] ?? '04999', // Other
                    'product_code' => $options['product_code'] ?? 'GIFTCARD',
                ]],
                'subtotal' => $amount,
                'total_tax' => 0, // Gift cards typically don't have tax
                'total' => $amount,
                'currency' => $options['currency'] ?? 'nok',
                'customer_id' => $options['customer_id'] ?? null,
                'customer_name' => $options['customer_name'] ?? null,
                'note' => $options['note'] ?? null,
            ];

            // Process payment for gift card purchase
            $metadata = array_merge([
                'gift_card_purchase' => true,
                'gift_card_code' => $code,
            ], $options['metadata'] ?? []);

            $result = $this->getPurchaseService()->processPurchase(
                $posSession,
                $paymentMethod,
                $cartData,
                $metadata
            );

            $charge = $result['charge'];

            // Calculate expiration date
            $expiresAt = $options['expires_at'] ?? null;
            if (!$expiresAt) {
                $settings = \App\Models\Setting::getForStore($posSession->store_id);
                $expirationDays = $settings->gift_card_expiration_days ?? 365;
                if ($expirationDays) {
                    $expiresAt = now()->addDays($expirationDays);
                }
            }

            // Create gift card
            $giftCard = GiftCard::create([
                'store_id' => $posSession->store_id,
                'code' => $code,
                'pin' => $pin,
                'initial_amount' => $amount,
                'balance' => $amount,
                'amount_redeemed' => 0,
                'currency' => $options['currency'] ?? 'nok',
                'status' => 'active',
                'purchased_at' => now(),
                'expires_at' => $expiresAt,
                'purchase_charge_id' => $charge->id,
                'purchased_by_user_id' => auth()->id(),
                'customer_id' => $options['customer_id'] ?? null,
                'notes' => $options['notes'] ?? null,
                'metadata' => $options['metadata'] ?? [],
            ]);

            // Create purchase transaction
            GiftCardTransaction::create([
                'gift_card_id' => $giftCard->id,
                'store_id' => $posSession->store_id,
                'type' => GiftCardTransaction::TYPE_PURCHASE,
                'amount' => $amount,
                'balance_before' => 0,
                'balance_after' => $amount,
                'charge_id' => $charge->id,
                'pos_session_id' => $posSession->id,
                'user_id' => auth()->id(),
                'notes' => 'Gift card purchased',
                'metadata' => [
                    'payment_method' => $paymentMethod->code,
                ],
            ]);

            // Log POS event (13023 - Gift card purchased)
            $this->logGiftCardEvent(
                $posSession,
                $giftCard,
                '13023',
                'Gift card purchased',
                $charge
            );

            DB::commit();

            return $giftCard->fresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Gift card purchase failed', [
                'error' => $e->getMessage(),
                'pos_session_id' => $posSession->id,
                'amount' => $amount,
            ]);
            throw $e;
        }
    }

    /**
     * Redeem a gift card
     */
    public function redeemGiftCard(
        string $code,
        int $amount,
        ConnectedCharge $charge,
        PosSession $posSession,
        ?string $pin = null
    ): GiftCardTransaction {
        return DB::transaction(function () use ($code, $amount, $charge, $posSession, $pin) {
            // Lock the gift card row for update to prevent race conditions
            $giftCard = GiftCard::where('code', $code)
                ->lockForUpdate()
                ->first();

            if (!$giftCard) {
                throw new \Exception('Gift card not found');
            }

            // Validate gift card
            if (!$giftCard->isValid()) {
                throw new \Exception('Gift card is not valid or has expired');
            }

            // Verify PIN if required
            if (!$giftCard->verifyPin($pin)) {
                throw new \Exception('Invalid PIN');
            }

            // Check balance
            if (!$giftCard->canRedeem($amount)) {
                throw new \Exception('Insufficient balance. Available: ' . $giftCard->formatted_balance);
            }

            // Store balance before
            $balanceBefore = $giftCard->balance;

            // Update gift card balance
            $giftCard->balance -= $amount;
            $giftCard->amount_redeemed += $amount;
            $giftCard->last_used_at = now();

            // Update status if fully redeemed
            if ($giftCard->balance <= 0) {
                $giftCard->status = 'redeemed';
            }

            $giftCard->save();

            // Create redemption transaction
            $transaction = GiftCardTransaction::create([
                'gift_card_id' => $giftCard->id,
                'store_id' => $posSession->store_id,
                'type' => GiftCardTransaction::TYPE_REDEMPTION,
                'amount' => -$amount, // Negative for redemption
                'balance_before' => $balanceBefore,
                'balance_after' => $giftCard->balance,
                'charge_id' => $charge->id,
                'pos_session_id' => $posSession->id,
                'user_id' => auth()->id(),
                'notes' => 'Gift card redeemed',
                'metadata' => [
                    'gift_card_code' => $code,
                ],
            ]);

            // Log POS event (13024 - Gift card redeemed)
            $posEvent = $this->logGiftCardEvent(
                $posSession,
                $giftCard,
                '13024',
                'Gift card redeemed',
                $charge
            );

            // Update transaction with event ID
            $transaction->pos_event_id = $posEvent->id;
            $transaction->save();

            return $transaction;
        });
    }

    /**
     * Validate a gift card
     */
    public function validateGiftCard(string $code, int $amount, ?string $pin = null): array
    {
        $giftCard = GiftCard::where('code', $code)->first();

        if (!$giftCard) {
            return [
                'valid' => false,
                'error' => 'Gift card not found',
            ];
        }

        if (!$giftCard->isValid()) {
            return [
                'valid' => false,
                'error' => 'Gift card is not valid or has expired',
                'status' => $giftCard->status,
            ];
        }

        if (!$giftCard->verifyPin($pin)) {
            return [
                'valid' => false,
                'error' => 'Invalid PIN',
            ];
        }

        if (!$giftCard->canRedeem($amount)) {
            return [
                'valid' => false,
                'error' => 'Insufficient balance',
                'balance' => $giftCard->balance,
                'formatted_balance' => $giftCard->formatted_balance,
            ];
        }

        return [
            'valid' => true,
            'gift_card' => [
                'id' => $giftCard->id,
                'code' => $giftCard->code,
                'balance' => $giftCard->balance, // Keep in øre for internal use
                'formatted_balance' => $giftCard->formatted_balance,
                'currency' => $giftCard->currency,
                'can_redeem_amount' => true,
            ],
        ];
    }

    /**
     * Get gift card by code
     */
    public function getGiftCardByCode(string $code): ?GiftCard
    {
        return GiftCard::where('code', $code)->first();
    }

    /**
     * Refund a gift card
     */
    public function refundGiftCard(
        GiftCard $giftCard,
        string $reason,
        PaymentMethod $refundPaymentMethod,
        PosSession $posSession
    ): GiftCardTransaction {
        DB::beginTransaction();

        try {
            if ($giftCard->status === 'refunded') {
                throw new \Exception('Gift card has already been refunded');
            }

            if ($giftCard->status === 'voided') {
                throw new \Exception('Cannot refund a voided gift card');
            }

            // Create refund charge (negative amount)
            $refundAmount = $giftCard->initial_amount;

            // Create cart data for refund
            $cartData = [
                'items' => [[
                    'product_id' => null,
                    'product_name' => 'Gavekort refusjon',
                    'quantity' => 1,
                    'unit_price' => -$refundAmount, // Negative for refund
                    'article_group_code' => '04999',
                    'product_code' => 'GIFTCARD-REFUND',
                ]],
                'subtotal' => -$refundAmount,
                'total_tax' => 0,
                'total' => -$refundAmount,
                'currency' => $giftCard->currency,
                'note' => "Refund for gift card {$giftCard->code}: {$reason}",
            ];

            // Process refund payment
            $metadata = [
                'gift_card_refund' => true,
                'gift_card_id' => $giftCard->id,
                'gift_card_code' => $giftCard->code,
                'refund_reason' => $reason,
            ];

            $result = $this->getPurchaseService()->processPurchase(
                $posSession,
                $refundPaymentMethod,
                $cartData,
                $metadata
            );

            $charge = $result['charge'];

            // Update gift card
            $balanceBefore = $giftCard->balance;
            $giftCard->status = 'refunded';
            $giftCard->balance = 0;
            $giftCard->save();

            // Create refund transaction
            $transaction = GiftCardTransaction::create([
                'gift_card_id' => $giftCard->id,
                'store_id' => $posSession->store_id,
                'type' => GiftCardTransaction::TYPE_REFUND,
                'amount' => -$refundAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => 0,
                'charge_id' => $charge->id,
                'pos_session_id' => $posSession->id,
                'user_id' => auth()->id(),
                'notes' => $reason,
                'metadata' => [
                    'refund_payment_method' => $refundPaymentMethod->code,
                ],
            ]);

            // Log POS event (13025 - Gift card refunded)
            $posEvent = $this->logGiftCardEvent(
                $posSession,
                $giftCard,
                '13025',
                'Gift card refunded: ' . $reason,
                $charge
            );

            $transaction->pos_event_id = $posEvent->id;
            $transaction->save();

            DB::commit();

            return $transaction;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Gift card refund failed', [
                'error' => $e->getMessage(),
                'gift_card_id' => $giftCard->id,
            ]);
            throw $e;
        }
    }

    /**
     * Void a gift card
     */
    public function voidGiftCard(GiftCard $giftCard, string $reason, PosSession $posSession): void
    {
        DB::beginTransaction();

        try {
            if ($giftCard->status === 'voided') {
                throw new \Exception('Gift card is already voided');
            }

            if ($giftCard->status === 'refunded') {
                throw new \Exception('Cannot void a refunded gift card');
            }

            // Update gift card
            $balanceBefore = $giftCard->balance;
            $giftCard->status = 'voided';
            $giftCard->balance = 0;
            $giftCard->save();

            // Create void transaction
            $transaction = GiftCardTransaction::create([
                'gift_card_id' => $giftCard->id,
                'store_id' => $posSession->store_id,
                'type' => GiftCardTransaction::TYPE_VOID,
                'amount' => 0,
                'balance_before' => $balanceBefore,
                'balance_after' => 0,
                'pos_session_id' => $posSession->id,
                'user_id' => auth()->id(),
                'notes' => $reason,
                'metadata' => [
                    'void_reason' => $reason,
                ],
            ]);

            // Log POS event (13026 - Gift card voided)
            $posEvent = $this->logGiftCardEvent(
                $posSession,
                $giftCard,
                '13026',
                'Gift card voided: ' . $reason,
                null
            );

            $transaction->pos_event_id = $posEvent->id;
            $transaction->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Gift card void failed', [
                'error' => $e->getMessage(),
                'gift_card_id' => $giftCard->id,
            ]);
            throw $e;
        }
    }

    /**
     * Adjust gift card balance
     */
    public function adjustBalance(
        GiftCard $giftCard,
        int $amount,
        string $reason,
        PosSession $posSession
    ): GiftCardTransaction {
        return DB::transaction(function () use ($giftCard, $amount, $reason, $posSession) {
            // Lock the gift card row for update
            $giftCard = GiftCard::where('id', $giftCard->id)
                ->lockForUpdate()
                ->first();

            if ($giftCard->status !== 'active') {
                throw new \Exception('Can only adjust balance for active gift cards');
            }

            $balanceBefore = $giftCard->balance;
            $giftCard->balance += $amount;

            // Ensure balance doesn't go negative
            if ($giftCard->balance < 0) {
                throw new \Exception('Balance cannot be negative');
            }

            // Update initial amount if increasing
            if ($amount > 0) {
                $giftCard->initial_amount += $amount;
            }

            $giftCard->save();

            // Create adjustment transaction
            $transaction = GiftCardTransaction::create([
                'gift_card_id' => $giftCard->id,
                'store_id' => $posSession->store_id,
                'type' => GiftCardTransaction::TYPE_ADJUSTMENT,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $giftCard->balance,
                'pos_session_id' => $posSession->id,
                'user_id' => auth()->id(),
                'notes' => $reason,
                'metadata' => [
                    'adjustment_reason' => $reason,
                ],
            ]);

            // Log POS event (13027 - Gift card balance adjusted)
            $posEvent = $this->logGiftCardEvent(
                $posSession,
                $giftCard,
                '13027',
                'Gift card balance adjusted: ' . $reason,
                null
            );

            $transaction->pos_event_id = $posEvent->id;
            $transaction->save();

            return $transaction;
        });
    }

    /**
     * Log gift card POS event
     */
    protected function logGiftCardEvent(
        PosSession $posSession,
        GiftCard $giftCard,
        string $eventCode,
        string $description,
        ?ConnectedCharge $charge = null
    ): PosEvent {
        $store = $posSession->store;
        $posDevice = $posSession->posDevice;

        return PosEvent::create([
            'store_id' => $store->id,
            'pos_device_id' => $posDevice?->id,
            'pos_session_id' => $posSession->id,
            'user_id' => auth()->id(),
            'related_charge_id' => $charge?->id,
            'event_code' => $eventCode,
            'event_type' => 'gift_card',
            'description' => $description,
            'event_data' => [
                'gift_card_id' => $giftCard->id,
                'gift_card_code' => $giftCard->code,
                'balance' => $giftCard->balance,
                'initial_amount' => $giftCard->initial_amount,
            ],
            'occurred_at' => now(),
        ]);
    }
}



