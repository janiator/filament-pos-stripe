<?php

namespace App\Http\Controllers\Api;

use App\Models\ConnectedCharge;
use App\Models\PosEvent;
use App\Models\PosSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosTransactionsController extends BaseApiController
{
    /**
     * Void a transaction (13014)
     * 
     * Voids a transaction that was created but not completed.
     * This is different from a refund - void cancels before completion.
     */
    public function void(Request $request, string $chargeId): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
            'pos_session_id' => 'nullable|exists:pos_sessions,id',
        ]);

        // Find the charge
        $charge = ConnectedCharge::where('id', $chargeId)
            ->whereHas('posSession', function ($query) use ($store) {
                $query->where('store_id', $store->id);
            })
            ->firstOrFail();

        // Check if charge can be voided (not already refunded, not already voided)
        if ($charge->refunded) {
            return response()->json([
                'message' => 'Cannot void a charge that has already been refunded',
            ], 400);
        }

        // Get session
        $session = $charge->posSession;
        if (isset($validated['pos_session_id'])) {
            $session = PosSession::where('id', $validated['pos_session_id'])
                ->where('store_id', $store->id)
                ->firstOrFail();
        }

        // Log void transaction event (13014)
        PosEvent::create([
            'store_id' => $store->id,
            'pos_device_id' => $session?->pos_device_id,
            'pos_session_id' => $session?->id,
            'user_id' => $request->user()->id,
            'related_charge_id' => $charge->id,
            'event_code' => PosEvent::EVENT_VOID_TRANSACTION,
            'event_type' => 'transaction',
            'description' => "Transaction voided for charge {$charge->stripe_charge_id}",
            'event_data' => [
                'charge_id' => $charge->id,
                'stripe_charge_id' => $charge->stripe_charge_id,
                'original_amount' => $charge->amount,
                'reason' => $validated['reason'] ?? null,
            ],
            'occurred_at' => now(),
        ]);

        // Update charge metadata to mark as voided
        $metadata = $charge->metadata ?? [];
        $metadata['voided'] = true;
        $metadata['voided_at'] = $this->formatDateTimeOslo(now());
        $metadata['voided_by'] = $request->user()->id;
        $metadata['void_reason'] = $validated['reason'] ?? null;
        $charge->metadata = $metadata;
        $charge->save();

        return response()->json([
            'message' => 'Transaction voided successfully',
            'charge' => [
                'id' => $charge->id,
                'stripe_charge_id' => $charge->stripe_charge_id,
                'amount' => $charge->amount,
                'voided' => true,
            ],
        ]);
    }

    /**
     * Create correction receipt (13015)
     * 
     * Creates a correction receipt for errors in previous transactions.
     */
    public function correctionReceipt(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'pos_session_id' => 'required|exists:pos_sessions,id',
            'related_charge_id' => 'nullable|exists:connected_charges,id',
            'correction_type' => 'required|string|in:price_correction,item_correction,payment_correction,other',
            'original_amount' => 'nullable|integer|min:0',
            'corrected_amount' => 'nullable|integer|min:0',
            'description' => 'required|string|max:1000',
            'correction_data' => 'nullable|array',
        ]);

        $session = PosSession::where('id', $validated['pos_session_id'])
            ->where('store_id', $store->id)
            ->firstOrFail();

        // Log correction receipt event (13015)
        $event = PosEvent::create([
            'store_id' => $store->id,
            'pos_device_id' => $session->pos_device_id,
            'pos_session_id' => $session->id,
            'user_id' => $request->user()->id,
            'related_charge_id' => $validated['related_charge_id'] ?? null,
            'event_code' => PosEvent::EVENT_CORRECTION_RECEIPT,
            'event_type' => 'transaction',
            'description' => $validated['description'],
            'event_data' => [
                'correction_type' => $validated['correction_type'],
                'original_amount' => $validated['original_amount'] ?? null,
                'corrected_amount' => $validated['corrected_amount'] ?? null,
                'correction_data' => $validated['correction_data'] ?? null,
            ],
            'occurred_at' => now(),
        ]);

        // Generate receipt for correction
        $receipt = \App\Models\Receipt::create([
            'store_id' => $store->id,
            'pos_session_id' => $session->id,
            'charge_id' => $validated['related_charge_id'],
            'user_id' => $request->user()->id,
            'receipt_type' => 'correction',
            'receipt_number' => \App\Models\Receipt::generateReceiptNumber($store->id, 'correction'),
            'receipt_data' => [
                'correction_type' => $validated['correction_type'],
                'description' => $validated['description'],
                'original_amount' => $validated['original_amount'] ?? null,
                'corrected_amount' => $validated['corrected_amount'] ?? null,
                'correction_data' => $validated['correction_data'] ?? null,
                'event_id' => $event->id,
            ],
        ]);

        return response()->json([
            'message' => 'Correction receipt created successfully',
            'event' => [
                'id' => $event->id,
                'event_code' => $event->event_code,
                'description' => $event->description,
            ],
            'receipt' => [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'receipt_type' => $receipt->receipt_type,
            ],
        ], 201);
    }

}

