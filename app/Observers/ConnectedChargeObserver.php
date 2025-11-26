<?php

namespace App\Observers;

use App\Models\ConnectedCharge;
use App\Models\PosEvent;
use App\Services\SafTCodeMapper;

class ConnectedChargeObserver
{
    /**
     * Handle the ConnectedCharge "created" event.
     */
    public function created(ConnectedCharge $charge): void
    {
        // Auto-map SAF-T codes
        $this->autoMapSafTCodes($charge);

        // Log sales receipt event (13012) for successful charges
        if ($charge->status === 'succeeded' && $charge->paid) {
            // Get store_id from pos_session or try to find via stripe_account_id
            $storeId = $charge->posSession?->store_id;
            if (!$storeId && $charge->stripe_account_id) {
                $store = \App\Models\Store::where('stripe_account_id', $charge->stripe_account_id)->first();
                $storeId = $store?->id;
            }
            
            PosEvent::create([
                'store_id' => $storeId,
                'pos_session_id' => $charge->pos_session_id,
                'user_id' => $charge->posSession?->user_id,
                'related_charge_id' => $charge->id,
                'event_code' => PosEvent::EVENT_SALES_RECEIPT,
                'event_type' => 'transaction',
                'description' => "Sales receipt for charge {$charge->stripe_charge_id}",
                'event_data' => [
                    'charge_id' => $charge->id,
                    'amount' => $charge->amount,
                    'currency' => $charge->currency,
                    'payment_method' => $charge->payment_method,
                ],
                'occurred_at' => $charge->paid_at ?? now(),
            ]);

            // Log payment method event (13016-13019)
            $paymentEventCode = SafTCodeMapper::mapPaymentMethodToEventCode($charge->payment_method);
            PosEvent::create([
                'store_id' => $storeId,
                'pos_session_id' => $charge->pos_session_id,
                'user_id' => $charge->posSession?->user_id,
                'related_charge_id' => $charge->id,
                'event_code' => $paymentEventCode,
                'event_type' => 'payment',
                'description' => "Payment method: {$charge->payment_method}",
                'event_data' => [
                    'charge_id' => $charge->id,
                    'payment_method' => $charge->payment_method,
                    'payment_code' => $charge->payment_code,
                ],
                'occurred_at' => $charge->paid_at ?? now(),
            ]);
        }
    }

    /**
     * Handle the ConnectedCharge "updated" event.
     */
    public function updated(ConnectedCharge $charge): void
    {
        // Log return receipt event (13013) when refunded
        if ($charge->wasChanged('refunded') && $charge->refunded && $charge->amount_refunded > 0) {
            // Get store ID from stripe_account_id
            $store = \App\Models\Store::where('stripe_account_id', $charge->stripe_account_id)->first();
            
            // Get session user_id if session exists
            $sessionUserId = null;
            if ($charge->pos_session_id) {
                $session = \App\Models\PosSession::find($charge->pos_session_id);
                $sessionUserId = $session?->user_id;
            }

            PosEvent::create([
                'store_id' => $store?->id,
                'pos_session_id' => $charge->pos_session_id,
                'user_id' => $sessionUserId,
                'related_charge_id' => $charge->id,
                'event_code' => PosEvent::EVENT_RETURN_RECEIPT,
                'event_type' => 'transaction',
                'description' => "Return receipt for charge {$charge->stripe_charge_id}",
                'event_data' => [
                    'charge_id' => $charge->id,
                    'refunded_amount' => $charge->amount_refunded,
                    'original_amount' => $charge->amount,
                ],
                'occurred_at' => now(),
            ]);
        }
    }

    /**
     * Auto-map SAF-T codes when charge is created
     */
    protected function autoMapSafTCodes(ConnectedCharge $charge): void
    {
        $updates = [];

        // Map payment code if not set or empty
        if (empty($charge->payment_code)) {
            $updates['payment_code'] = SafTCodeMapper::mapPaymentMethodToCode($charge->payment_method);
        }

        // Map transaction code if not set or empty
        if (empty($charge->transaction_code)) {
            $updates['transaction_code'] = SafTCodeMapper::mapTransactionToCode($charge);
        }

        // Update if we have changes (use updateQuietly to avoid triggering events)
        if (!empty($updates)) {
            $charge->updateQuietly($updates);
        }
    }
}
