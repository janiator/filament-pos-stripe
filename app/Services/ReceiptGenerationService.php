<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\ConnectedCharge;
use App\Models\Store;
use App\Models\PosSession;
use App\Models\ConnectedProduct;

class ReceiptGenerationService
{
    protected ReceiptTemplateService $templateService;

    public function __construct(ReceiptTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }
    /**
     * Generate a sales receipt for a charge
     */
    public function generateSalesReceipt(ConnectedCharge $charge, ?PosSession $session = null): Receipt
    {
        $session = $session ?? $charge->posSession;
        
        // Get store from session or find by stripe_account_id
        $store = $session?->store;
        if (!$store && $charge->stripe_account_id) {
            $store = Store::where('stripe_account_id', $charge->stripe_account_id)->first();
        }
        
        if (!$store) {
            throw new \Exception('Cannot generate receipt: Store not found for charge');
        }

        // Get items from charge metadata or create default
        $items = [];
        $metadata = is_array($charge->metadata) ? $charge->metadata : json_decode($charge->metadata ?? '{}', true);
        
        if (isset($metadata['items']) && is_array($metadata['items'])) {
            $items = $metadata['items'];
        } else {
            // Try to get product from charge metadata
            $productId = $metadata['product_id'] ?? null;
            if ($productId) {
                $product = ConnectedProduct::find($productId);
                if ($product) {
                    $quantity = $metadata['quantity'] ?? 1;
                    $unitPrice = ($charge->amount / 100) / $quantity;
                    $items[] = [
                        'name' => $product->name,
                        'quantity' => $quantity,
                        'unit_price' => number_format($unitPrice, 2, ',', ' '),
                        'line_total' => number_format($charge->amount / 100, 2, ',', ' '),
                    ];
                }
            } else {
                // Fallback: single item
                $items[] = [
                    'name' => $charge->description ?? 'Vare',
                    'quantity' => 1,
                    'unit_price' => number_format($charge->amount / 100, 2, ',', ' '),
                    'line_total' => number_format($charge->amount / 100, 2, ',', ' '),
                ];
            }
        }

        $storeMetadata = is_array($store->metadata) ? $store->metadata : json_decode($store->metadata ?? '{}', true);

        $receiptData = [
            'store' => [
                'name' => $store->name,
                'address' => $storeMetadata['address'] ?? '',
                'organization_number' => $storeMetadata['organization_number'] ?? '',
            ],
            'receipt_number' => Receipt::generateReceiptNumber($store->id, 'sales'),
            'date' => $charge->paid_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
            'transaction_id' => $charge->stripe_charge_id,
            'session_number' => $session?->session_number,
            'cashier' => $session?->user?->name ?? 'Unknown',
            'items' => $items,
            'subtotal' => $charge->amount / 100,
            'tax' => $this->calculateTax($charge),
            'total' => $charge->amount / 100,
            'payment_method' => $charge->payment_method,
            'payment_code' => $charge->payment_code,
            'tip_amount' => $charge->tip_amount > 0 ? $charge->tip_amount / 100 : null,
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

        // Render and save XML template
        $this->templateService->renderAndSave($receipt);

        return $receipt;
    }

    /**
     * Generate a return receipt
     */
    public function generateReturnReceipt(ConnectedCharge $charge, Receipt $originalReceipt): Receipt
    {
        $session = $charge->posSession;
        
        // Get store from session or find by stripe_account_id
        $store = $session?->store;
        if (!$store && $charge->stripe_account_id) {
            $store = Store::where('stripe_account_id', $charge->stripe_account_id)->first();
        }
        
        if (!$store) {
            throw new \Exception('Cannot generate receipt: Store not found for charge');
        }

        // Get items from original receipt or charge
        $items = [];
        if (isset($originalReceipt->receipt_data['items'])) {
            // Use original items but with negative amounts
            foreach ($originalReceipt->receipt_data['items'] as $item) {
                $items[] = [
                    'name' => $item['name'] ?? $item['description'] ?? 'Vare',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? number_format($charge->amount_refunded / 100, 2, ',', ' '),
                    'line_total' => '-' . ($item['line_total'] ?? number_format($charge->amount_refunded / 100, 2, ',', ' ')),
                ];
            }
        } else {
            // Fallback: single item
            $items[] = [
                'name' => $charge->description ?? 'Retur',
                'quantity' => 1,
                'unit_price' => number_format($charge->amount_refunded / 100, 2, ',', ' '),
                'line_total' => '-' . number_format($charge->amount_refunded / 100, 2, ',', ' '),
            ];
        }

        $storeMetadata = is_array($store->metadata) ? $store->metadata : json_decode($store->metadata ?? '{}', true);

        $receiptData = [
            'store' => [
                'name' => $store->name,
                'address' => $storeMetadata['address'] ?? '',
                'organization_number' => $storeMetadata['organization_number'] ?? '',
            ],
            'receipt_number' => Receipt::generateReceiptNumber($store->id, 'return'),
            'date' => now()->format('Y-m-d H:i:s'),
            'original_receipt_number' => $originalReceipt->receipt_number,
            'transaction_id' => $charge->stripe_charge_id,
            'refund_amount' => $charge->amount_refunded / 100,
            'original_amount' => $charge->amount / 100,
            'items' => $items,
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

        // Render and save XML template
        $this->templateService->renderAndSave($receipt);

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

