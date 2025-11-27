<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\ReceiptTemplate;
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
        $this->mustache = new Mustache_Engine();
    }

    /**
     * Render receipt as Epson ePOS XML
     */
    public function renderReceipt(Receipt $receipt): string
    {
        $templateType = $receipt->receipt_type;
        $storeId = $receipt->store_id;
        
        // Try to get template from database first (store-specific or global)
        $template = $this->getTemplate($storeId, $templateType);
        
        // If no database template, fall back to file
        if (!$template) {
            $template = $this->getTemplateFromFile($templateType);
        }
        
        $data = $this->prepareReceiptData($receipt);
        
        // Use Mustache to render the template
        $xml = $this->mustache->render($template, $data);
        
        // Sanitize XML: Remove invalid <line> elements that cause schema errors
        $xml = $this->sanitizeXml($xml);
        
        return $xml;
    }

    /**
     * Sanitize XML by removing invalid elements that cause schema errors
     */
    protected function sanitizeXml(string $xml): string
    {
        // Remove <line> elements - they're not properly supported in ePOS-Print XML schema
        // and cause SchemaError. Use text dashes instead.
        $xml = preg_replace('/<line[^>]*\/?>/i', '', $xml);
        
        return $xml;
    }

    /**
     * Get template from database (store-specific or global)
     */
    protected function getTemplate(?int $storeId, string $templateType): ?string
    {
        $receiptTemplate = ReceiptTemplate::getTemplate($storeId, $templateType);
        
        return $receiptTemplate?->content;
    }

    /**
     * Get template from file (fallback)
     */
    protected function getTemplateFromFile(string $templateType): string
    {
        $templateName = $this->getTemplateName($templateType);
        $templatePath = $this->templatePath . '/' . $templateName;
        
        if (!File::exists($templatePath)) {
            throw new \RuntimeException("Receipt template not found: {$templatePath}");
        }
        
        return File::get($templatePath);
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
        
        if ($charge) {
            $metadata = is_array($charge->metadata) ? $charge->metadata : json_decode($charge->metadata ?? '{}', true);
            
            if (isset($metadata['card_brand'])) {
                $cardBrand = $metadata['card_brand'];
            }
            if (isset($metadata['card_last4'])) {
                $cardLast4 = $metadata['card_last4'];
            }
            if (isset($metadata['terminal_number'])) {
                $terminalNumber = $metadata['terminal_number'];
            }
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

