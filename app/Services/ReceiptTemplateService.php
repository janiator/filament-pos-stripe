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
        
        // Pre-process: Merge consecutive empty text elements followed by a text element with content
        // Pattern: <text .../> (possibly multiple) followed by <text ...>content</text>
        // This handles the common case where attributes are split across multiple elements
        $xml = preg_replace_callback(
            '/(<text[^>]*\/>\s*)+<text([^>]*)>([^<]*)<\/text>/i',
            function($matches) {
                // Extract all attributes from empty text elements
                preg_match_all('/<text([^>]*)\/>/i', $matches[0], $emptyMatches);
                $allAttributes = [];
                
                foreach ($emptyMatches[1] as $attrString) {
                    preg_match_all('/(\w+)="([^"]*)"/', $attrString, $attrMatches);
                    for ($i = 0; $i < count($attrMatches[1]); $i++) {
                        $key = $attrMatches[1][$i];
                        $value = $attrMatches[2][$i];
                        // Keep the last value if attribute appears multiple times
                        $allAttributes[$key] = $value;
                    }
                }
                
                // Extract attributes from the text element with content
                preg_match_all('/(\w+)="([^"]*)"/', $matches[2], $contentAttrMatches);
                for ($i = 0; $i < count($contentAttrMatches[1]); $i++) {
                    $key = $contentAttrMatches[1][$i];
                    $value = $contentAttrMatches[2][$i];
                    $allAttributes[$key] = $value;
                }
                
                // Build merged text element
                $attrString = '';
                foreach ($allAttributes as $key => $value) {
                    $attrString .= ' ' . $key . '="' . htmlspecialchars($value, ENT_XML1, 'UTF-8') . '"';
                }
                
                return '<text' . $attrString . '>' . $matches[3] . '</text>';
            },
            $xml
        );
        
        // Remove standalone empty text elements (not followed by text with content)
        $xml = preg_replace('/<text[^>]*\/>\s*(?=\s*<(?!text)[^\/]|<\/|$)/i', '', $xml);
        
        // Use DOMDocument for robust XML processing to fix schema errors
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;
            
            // Load XML with error suppression to handle minor issues
            $previousErrorReporting = libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xml);
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorReporting);
            
            if ($loaded) {
                $xpath = new \DOMXPath($dom);
                $xpath->registerNamespace('epos', 'http://www.epson-pos.com/schemas/2011/03/epos-print');
                $xpath->registerNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
                
                // Process each parent node to merge consecutive text siblings
                $parents = $xpath->query('//*[epos:text or text]');
                
                foreach ($parents as $parent) {
                    $children = [];
                    foreach ($parent->childNodes as $child) {
                        if ($child instanceof \DOMElement && 
                            ($child->localName === 'text' || $child->nodeName === 'text')) {
                            $children[] = $child;
                        }
                    }
                    
                    // Process consecutive text elements
                    $i = 0;
                    while ($i < count($children)) {
                        $current = $children[$i];
                        $textContent = trim($current->textContent);
                        $hasContent = $textContent !== '';
                        
                        if (!$hasContent) {
                            // Look ahead for text elements with content
                            $emptyElements = [$current];
                            $j = $i + 1;
                            
                            // Collect consecutive empty text elements
                            while ($j < count($children)) {
                                $next = $children[$j];
                                $nextContent = trim($next->textContent);
                                if ($nextContent === '') {
                                    $emptyElements[] = $next;
                                    $j++;
                                } else {
                                    // Found element with content - merge attributes
                                    foreach ($emptyElements as $empty) {
                                        foreach ($empty->attributes as $attr) {
                                            if (!$next->hasAttribute($attr->nodeName)) {
                                                $next->setAttribute($attr->nodeName, $attr->nodeValue);
                                            }
                                        }
                                        if ($empty->parentNode) {
                                            $empty->parentNode->removeChild($empty);
                                        }
                                    }
                                    $i = $j;
                                    break;
                                }
                            }
                            
                            // If no element with content found, remove empty elements
                            if ($j >= count($children)) {
                                foreach ($emptyElements as $empty) {
                                    if ($empty->parentNode) {
                                        $empty->parentNode->removeChild($empty);
                                    }
                                }
                                break;
                            }
                        } else {
                            $i++;
                        }
                    }
                }
                
                // Final pass: remove any remaining empty text elements
                $textElements = $xpath->query('//epos:text | //text');
                $elementsToRemove = [];
                
                foreach ($textElements as $textElement) {
                    $textContent = trim($textElement->textContent);
                    if ($textContent === '' && 
                        !$textElement->hasAttribute('font') && 
                        !$textElement->hasAttribute('width') && 
                        !$textElement->hasAttribute('height')) {
                        $elementsToRemove[] = $textElement;
                    }
                }
                
                foreach ($elementsToRemove as $element) {
                    if ($element->parentNode) {
                        $element->parentNode->removeChild($element);
                    }
                }
                
                // Get cleaned XML - save full document to preserve XML declaration and envelope structure
                $xml = $dom->saveXML();
                
                // Ensure we have the XML declaration if the template included it
                if (!preg_match('/^<\?xml/', $xml)) {
                    // If template had XML declaration, preserve it by checking original
                    // Otherwise, the DOMDocument will add it automatically
                }
            } else {
                // If DOM loading failed, try regex-based cleanup as fallback
                \Log::warning('XML sanitization DOM loading failed, using regex fallback');
                
                // Remove empty self-closing text elements
                $xml = preg_replace('/<text[^>]*\/>\s*/i', '', $xml);
            }
        } catch (\Exception $e) {
            // If DOM processing fails, use regex-based cleanup
            \Log::warning('XML sanitization DOM processing failed: ' . $e->getMessage());
            
            // Fallback: Remove empty self-closing text elements
            $xml = preg_replace('/<text[^>]*\/>\s*/i', '', $xml);
        }
        
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
        $receiptData = $receipt->receipt_data ?? [];
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

        // Format payment method display (handle split payments)
        $receiptData = $receipt->receipt_data ?? [];
        $isSplitPayment = $receiptData['is_split_payment'] ?? false;
        
        if ($isSplitPayment && isset($receiptData['payments'])) {
            $paymentMethods = [];
            foreach ($receiptData['payments'] as $payment) {
                $paymentMethods[] = $this->formatPaymentMethod($payment['method'] ?? 'unknown') . 
                    ' ' . number_format($payment['amount'], 2, ',', ' ') . ' kr';
            }
            $paymentMethodDisplay = implode(' + ', $paymentMethods);
        } else {
            $paymentMethodDisplay = $this->formatPaymentMethod($charge?->payment_method ?? 'unknown');
        }

        // Format date/time in Oslo timezone
        // Use date from receipt_data if available (from charge paid_at), otherwise use created_at
        if (isset($receipt->receipt_data['date'])) {
            // Parse the date string (already in Oslo timezone from ReceiptGenerationService)
            $dateTime = \Carbon\Carbon::parse($receipt->receipt_data['date'], 'Europe/Oslo')
                ->format('d.m.Y H:i');
        } else {
            $dateTime = $receipt->created_at->setTimezone('Europe/Oslo')->format('d.m.Y H:i');
        }

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
            'is_split_payment' => $isSplitPayment,
            'payments' => $receiptData['payments'] ?? [],
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

