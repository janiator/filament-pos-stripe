<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\ConnectedCharge;
use App\Models\Store;
use App\Models\PosSession;

class ReceiptGenerationService
{
    /**
     * Generate a sales receipt for a charge
     */
    public function generateSalesReceipt(ConnectedCharge $charge, ?PosSession $session = null): Receipt
    {
        $store = $charge->store;
        $session = $session ?? $charge->posSession;

        $receiptData = [
            'store' => [
                'name' => $store->name,
                'address' => $store->metadata['address'] ?? '',
                'organization_number' => $store->metadata['organization_number'] ?? '',
            ],
            'receipt_number' => Receipt::generateReceiptNumber($store->id, 'sales'),
            'date' => $charge->paid_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
            'transaction_id' => $charge->stripe_charge_id,
            'session_number' => $session?->session_number,
            'cashier' => $session?->user?->name ?? 'Unknown',
            'items' => [
                [
                    'description' => $charge->description ?? 'Sale',
                    'quantity' => 1,
                    'price' => $charge->amount / 100,
                    'total' => $charge->amount / 100,
                ],
            ],
            'subtotal' => $charge->amount / 100,
            'tax' => $this->calculateTax($charge),
            'total' => $charge->amount / 100,
            'payment_method' => $charge->payment_method,
            'payment_code' => $charge->payment_code,
            'tip_amount' => $charge->tip_amount / 100,
        ];

        $receipt = Receipt::create([
            'store_id' => $store->id,
            'pos_session_id' => $session?->id,
            'charge_id' => $charge->id,
            'user_id' => $session?->user_id,
            'receipt_number' => $receiptData['receipt_number'],
            'receipt_type' => 'sales',
            'receipt_data' => $receiptData,
        ]);

        return $receipt;
    }

    /**
     * Generate a return receipt
     */
    public function generateReturnReceipt(ConnectedCharge $charge, Receipt $originalReceipt): Receipt
    {
        $store = $charge->store;
        $session = $charge->posSession;

        $receiptData = [
            'store' => [
                'name' => $store->name,
                'address' => $store->metadata['address'] ?? '',
            ],
            'receipt_number' => Receipt::generateReceiptNumber($store->id, 'return'),
            'date' => now()->format('Y-m-d H:i:s'),
            'original_receipt_number' => $originalReceipt->receipt_number,
            'transaction_id' => $charge->stripe_charge_id,
            'refund_amount' => $charge->amount_refunded / 100,
            'original_amount' => $charge->amount / 100,
        ];

        $receipt = Receipt::create([
            'store_id' => $store->id,
            'pos_session_id' => $session?->id,
            'charge_id' => $charge->id,
            'user_id' => $session?->user_id,
            'receipt_number' => $receiptData['receipt_number'],
            'receipt_type' => 'return',
            'original_receipt_id' => $originalReceipt->id,
            'receipt_data' => $receiptData,
        ]);

        return $receipt;
    }

    /**
     * Calculate tax amount
     */
    protected function calculateTax(ConnectedCharge $charge): float
    {
        // Default 25% VAT in Norway
        $taxRate = 0.25;
        return round(($charge->amount / 100) * $taxRate / (1 + $taxRate), 2);
    }
}

