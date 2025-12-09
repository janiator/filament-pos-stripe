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
     * Generate receipt XML for printing
     *
     * @param Receipt $receipt
     * @return string
     */
    public function generateReceiptXml(Receipt $receipt): string
    {
        return $this->templateService->renderReceipt($receipt);
    }

    /**
     * Generate a sales receipt for a charge (single or split payment)
     *
     * @param ConnectedCharge|array $chargeOrCharges Primary charge or array of charges for split payment
     * @param PosSession|null $session
     * @return Receipt
     */
    public function generateSalesReceipt(ConnectedCharge|array $chargeOrCharges, ?PosSession $session = null): Receipt
    {
        // Handle both single charge and array of charges (split payment)
        $charges = is_array($chargeOrCharges) ? $chargeOrCharges : [$chargeOrCharges];
        $primaryCharge = $charges[0];
        $isSplitPayment = count($charges) > 1;

        $session = $session ?? $primaryCharge->posSession;
        
        // Get store from session or find by stripe_account_id
        $store = $session?->store;
        if (!$store && $primaryCharge->stripe_account_id) {
            $store = Store::where('stripe_account_id', $primaryCharge->stripe_account_id)->first();
        }
        
        if (!$store) {
            throw new \Exception('Cannot generate receipt: Store not found for charge');
        }

        // Get items from primary charge metadata
        $items = [];
        $metadata = is_array($primaryCharge->metadata) ? $primaryCharge->metadata : json_decode($primaryCharge->metadata ?? '{}', true);
        
        if (isset($metadata['items']) && is_array($metadata['items'])) {
            $items = $metadata['items'];
        } else {
            // Try to get product from charge metadata
            $productId = $metadata['product_id'] ?? null;
            if ($productId) {
                $product = ConnectedProduct::find($productId);
                if ($product) {
                    $quantity = $metadata['quantity'] ?? 1;
                    $totalAmount = array_sum(array_column($charges, 'amount'));
                    $unitPrice = ($totalAmount / 100) / $quantity;
                    $items[] = [
                        'name' => $product->name,
                        'quantity' => $quantity,
                        'unit_price' => number_format($unitPrice, 2, ',', ' '),
                        'line_total' => number_format($totalAmount / 100, 2, ',', ' '),
                    ];
                }
            } else {
                // Fallback: single item
                $totalAmount = array_sum(array_column($charges, 'amount'));
                $items[] = [
                    'name' => $primaryCharge->description ?? 'Vare',
                    'quantity' => 1,
                    'unit_price' => number_format($totalAmount / 100, 2, ',', ' '),
                    'line_total' => number_format($totalAmount / 100, 2, ',', ' '),
                ];
            }
        }

        // Calculate totals
        $totalAmount = array_sum(array_column($charges, 'amount'));
        $subtotal = $metadata['subtotal'] ?? ($totalAmount / 100);
        $totalDiscounts = $metadata['total_discounts'] ?? 0;
        $totalTax = $metadata['total_tax'] ?? $this->calculateTaxFromAmount($totalAmount);
        $tipAmount = $metadata['tip_amount'] ?? 0;

        // Build payment breakdown for split payments
        $payments = [];
        foreach ($charges as $charge) {
            $chargeMetadata = is_array($charge->metadata) ? $charge->metadata : json_decode($charge->metadata ?? '{}', true);
            $payments[] = [
                'method' => $charge->payment_method,
                'amount' => $charge->amount / 100,
                'payment_code' => $charge->payment_code,
                'transaction_code' => $charge->transaction_code,
            ];
        }

        $storeMetadata = is_array($store->metadata) ? $store->metadata : json_decode($store->metadata ?? '{}', true);

        $receiptData = [
            'store' => [
                'name' => $store->name,
                'address' => $storeMetadata['address'] ?? '',
                'organization_number' => $storeMetadata['organization_number'] ?? '',
            ],
            'receipt_number' => Receipt::generateReceiptNumber($store->id, 'sales'),
            'date' => ($primaryCharge->paid_at?->setTimezone('Europe/Oslo') ?? now()->setTimezone('Europe/Oslo'))->format('Y-m-d H:i:s'),
            'transaction_id' => $primaryCharge->stripe_charge_id ?? $primaryCharge->id, // Use charge ID if no Stripe charge ID (for cash payments)
            'session_number' => $session?->session_number,
            'cashier' => $session?->user?->name ?? 'Unknown',
            'items' => $items,
            'subtotal' => $subtotal,
            'total_discounts' => $totalDiscounts,
            'tax' => $totalTax,
            'total' => $totalAmount / 100,
            'is_split_payment' => $isSplitPayment,
            'payments' => $payments,
            'payment_method' => $isSplitPayment ? 'split' : $primaryCharge->payment_method,
            'payment_code' => $isSplitPayment ? null : $primaryCharge->payment_code,
            'tip_amount' => $tipAmount > 0 ? ($tipAmount / 100) : null,
            'charge_ids' => array_column($charges, 'id'),
        ];

        $receipt = Receipt::create([
            'store_id' => $store->id,
            'pos_session_id' => $session?->id,
            'charge_id' => $primaryCharge->id, // Primary charge for backward compatibility
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
     * Calculate tax from amount
     */
    protected function calculateTaxFromAmount(int $amountInOre): float
    {
        // Default 25% VAT in Norway
        $taxRate = 0.25;
        return round(($amountInOre / 100) * $taxRate / (1 + $taxRate), 2);
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
            'date' => now()->setTimezone('Europe/Oslo')->format('Y-m-d H:i:s'),
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
     * Generate a delivery receipt for deferred payment (Utleveringskvittering)
     * Complies with Kassasystemforskriften § 2-8-7
     * Used for credit sales that will be invoiced/paid later (e.g., dry cleaning)
     *
     * @param ConnectedCharge $charge
     * @param PosSession|null $session
     * @return Receipt
     */
    public function generateDeliveryReceipt(ConnectedCharge $charge, ?PosSession $session = null): Receipt
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

        // Get items from charge metadata
        $items = [];
        $metadata = is_array($charge->metadata) ? $charge->metadata : json_decode($charge->metadata ?? '{}', true);
        
        if (isset($metadata['items']) && is_array($metadata['items'])) {
            $items = $metadata['items'];
        } else {
            // Fallback: single item
            $items[] = [
                'name' => $charge->description ?? 'Vare/Tjeneste',
                'quantity' => 1,
                'unit_price' => number_format($charge->amount / 100, 2, ',', ' '),
                'line_total' => number_format($charge->amount / 100, 2, ',', ' '),
            ];
        }

        // Calculate totals
        $subtotal = $metadata['subtotal'] ?? ($charge->amount / 100);
        $totalDiscounts = $metadata['total_discounts'] ?? 0;
        $totalTax = $metadata['total_tax'] ?? $this->calculateTaxFromAmount($charge->amount);

        $storeMetadata = is_array($store->metadata) ? $store->metadata : json_decode($store->metadata ?? '{}', true);

        $receiptData = [
            'store' => [
                'name' => $store->name,
                'address' => $storeMetadata['address'] ?? '',
                'organization_number' => $storeMetadata['organization_number'] ?? '',
            ],
            'receipt_number' => Receipt::generateReceiptNumber($store->id, 'delivery'),
            'date' => now()->setTimezone('Europe/Oslo')->format('Y-m-d H:i:s'),
            'transaction_id' => $charge->id, // Use charge ID for deferred payments
            'session_number' => $session?->session_number,
            'cashier' => $session?->user?->name ?? 'Unknown',
            'items' => $items,
            'subtotal' => $subtotal,
            'total_discounts' => $totalDiscounts,
            'tax' => $totalTax,
            'total' => $charge->amount / 100,
            'currency' => strtoupper($charge->currency),
            'deferred_reason' => $metadata['deferred_reason'] ?? 'Betaling ved henting',
            'customer_id' => $metadata['customer_id'] ?? null,
            'customer_name' => $metadata['customer_name'] ?? null,
        ];

        $receipt = Receipt::create([
            'store_id' => $store->id,
            'pos_session_id' => $session?->id,
            'charge_id' => $charge->id,
            'user_id' => $session?->user_id,
            'receipt_number' => $receiptData['receipt_number'],
            'receipt_type' => 'delivery',
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
        return $this->calculateTaxFromAmount($charge->amount);
    }
}

