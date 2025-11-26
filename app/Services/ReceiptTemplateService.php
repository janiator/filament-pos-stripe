<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\ConnectedCharge;
use App\Models\Store;
use App\Models\PosSession;
use Mustache_Engine;
use Illuminate\Support\Facades\File;

class ReceiptTemplateService
{
    protected Mustache_Engine $mustache;
    protected string $templatePath;

    public function __construct()
    {
        $this->templatePath = base_path('resources/receipt-templates/epson');
        $this->mustache = new Mustache_Engine([
            'loader' => new \Mustache_Loader_FilesystemLoader($this->templatePath),
        ]);
    }

    /**
     * Render receipt as Epson ePOS XML
     */
    public function renderReceipt(Receipt $receipt): string
    {
        $templateName = $this->getTemplateName($receipt->receipt_type);
        
        $data = $this->prepareReceiptData($receipt);
        
        // Use Mustache to render the template
        // Remove .xml extension for Mustache loader
        $templateKey = str_replace('.xml', '', $templateName);
        return $this->mustache->render($templateKey, $data);
    }

    /**
     * Get template filename for receipt type
     */
    protected function getTemplateName(string $receiptType): string
    {
        return match($receiptType) {
            'sales' => 'sales-receipt.xml',
            'return' => 'return-receipt.xml',
            'copy' => 'copy-receipt.xml',
            'steb' => 'steb-receipt.xml',
            'provisional' => 'provisional-receipt.xml',
            'training' => 'training-receipt.xml',
            'delivery' => 'delivery-receipt.xml',
            default => 'sales-receipt.xml',
        };
    }

    /**
     * Prepare data for template rendering
     */
    protected function prepareReceiptData(Receipt $receipt): array
    {
        $store = $receipt->store;
        $charge = $receipt->charge;
        $session = $receipt->posSession;
        $user = $receipt->user;

        // Get store metadata
        $storeMetadata = is_array($store->metadata) ? $store->metadata : json_decode($store->metadata ?? '{}', true);
        
        // Calculate VAT (25% standard in Norway)
        $vatRate = 25.0;
        $totalAmount = $charge ? ($charge->amount / 100) : ($receipt->receipt_data['total'] ?? 0);
        $vatBase = round($totalAmount / (1 + ($vatRate / 100)), 2);
        $vatAmount = round($totalAmount - $vatBase, 2);

        // Prepare items
        $items = [];
        if ($charge && isset($receipt->receipt_data['items'])) {
            $items = $receipt->receipt_data['items'];
        } elseif ($charge) {
            // Fallback: create single item from charge
            $items[] = [
                'name' => $charge->description ?? 'Vare',
                'quantity' => 1,
                'unit_price' => number_format($totalAmount, 2, ',', ' '),
                'line_total' => number_format($totalAmount, 2, ',', ' '),
            ];
        }

        // Format payment method display
        $paymentMethodDisplay = $this->formatPaymentMethod($charge?->payment_method ?? 'unknown');

        // Format date/time
        $dateTime = $receipt->created_at->format('d.m.Y H:i');

        // Get card details if available
        $cardBrand = null;
        $cardLast4 = null;
        $terminalNumber = null;
        
        if ($charge && isset($charge->metadata['card_brand'])) {
            $cardBrand = $charge->metadata['card_brand'];
        }
        if ($charge && isset($charge->metadata['card_last4'])) {
            $cardLast4 = $charge->metadata['card_last4'];
        }
        if ($charge && isset($charge->metadata['terminal_number'])) {
            $terminalNumber = $charge->metadata['terminal_number'];
        }

        return [
            'store_name' => $store->name,
            'organization_number' => $storeMetadata['organization_number'] ?? '',
            'store_address' => $storeMetadata['address'] ?? '',
            'session_number' => $session?->session_number ?? 'N/A',
            'cashier_name' => $user?->name ?? 'N/A',
            'transaction_id' => $charge?->stripe_charge_id ?? $receipt->receipt_data['transaction_id'] ?? 'N/A',
            'receipt_number' => $receipt->receipt_number,
            'date_time' => $dateTime,
            'items' => $items,
            'total_amount' => number_format($totalAmount, 2, ',', ' '),
            'currency' => strtoupper($charge?->currency ?? 'NOK'),
            'vat_rate' => (string) $vatRate,
            'vat_base' => number_format($vatBase, 2, ',', ' '),
            'vat_amount' => number_format($vatAmount, 2, ',', ' '),
            'payment_method_display' => $paymentMethodDisplay,
            'terminal_number' => $terminalNumber,
            'card_brand' => $cardBrand,
            'card_last4' => $cardLast4,
            'tip_amount' => $charge && $charge->tip_amount > 0 
                ? number_format($charge->tip_amount / 100, 2, ',', ' ') 
                : null,
            'original_receipt_number' => $receipt->originalReceipt?->receipt_number ?? null,
        ];
    }

    /**
     * Format payment method for display
     */
    protected function formatPaymentMethod(?string $paymentMethod): string
    {
        return match($paymentMethod) {
            'cash' => 'Kontant',
            'card' => 'Kort',
            'credit_card' => 'Kredittkort',
            'mobile' => 'Mobil',
            'gift_token' => 'Gavekort',
            'customer_card' => 'Kundekort',
            'loyalty' => 'Lojalitetspoeng',
            default => 'Ukjent',
        };
    }

    /**
     * Render receipt and save XML to receipt data
     */
    public function renderAndSave(Receipt $receipt): Receipt
    {
        $xml = $this->renderReceipt($receipt);
        
        $receiptData = $receipt->receipt_data ?? [];
        $receiptData['xml'] = $xml;
        $receiptData['rendered_at'] = now()->toISOString();
        
        $receipt->update([
            'receipt_data' => $receiptData,
        ]);

        return $receipt;
    }
}

