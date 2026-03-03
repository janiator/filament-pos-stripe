<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\ReceiptTemplate;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mustache_Engine;

class ReceiptTemplateService
{
    protected Mustache_Engine $mustache;

    protected string $templatePath;

    public function __construct()
    {
        $this->templatePath = base_path('resources/receipt-templates/epson');
        $this->mustache = new Mustache_Engine;
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
        if (! $template) {
            $template = $this->getTemplateFromFile($templateType);
        }

        // Copy receipts: use original receipt's full template data so copy shows same lines and transaction id; only swap receipt number
        $originalReceipt = null;
        if ($receipt->receipt_type === 'copy') {
            if ($receipt->original_receipt_id) {
                $originalReceipt = $receipt->originalReceipt ?? Receipt::find($receipt->original_receipt_id);
            }
            // Fallback: copy may have been created via receipts/generate with no original_receipt_id - find sales/return receipt for same charge
            if (! $originalReceipt && $receipt->charge_id) {
                $originalReceipt = Receipt::where('charge_id', $receipt->charge_id)
                    ->whereIn('receipt_type', ['sales', 'return'])
                    ->orderByDesc('id')
                    ->first();
            }
            if ($originalReceipt) {
                $data = $this->prepareReceiptData($originalReceipt);
                $data['receipt_number'] = $receipt->receipt_number;
                $data['original_receipt_number'] = $originalReceipt->receipt_number;
            } else {
                $data = $this->prepareReceiptData($receipt);
            }
        } else {
            $data = $this->prepareReceiptData($receipt);
        }

        // Use Mustache to render the template
        $xml = $this->mustache->render($template, $data);

        // Sanitize XML: Remove invalid <line> elements that cause schema errors
        $xml = $this->sanitizeXml($xml);

        return $xml;
    }

    /**
     * Convert rendered receipt XML to HTML for on-screen preview (receipt-like layout).
     */
    public function renderReceiptAsHtml(Receipt $receipt): string
    {
        $xml = $this->renderReceipt($receipt);
        $xml = preg_replace('/<image([^>]*)>\K[\s\S]*?(?=<\/image>)/', '[IMAGE]', $xml);

        return $this->xmlToHtml($xml);
    }

    /**
     * Render a receipt template (XML content) with sample data and return HTML for preview.
     * Used on the receipt template edit page.
     */
    public function renderTemplatePreviewAsHtml(string $templateContent, string $templateType): string
    {
        try {
            $data = $this->getSampleReceiptData($templateType);
            $xml = $this->renderTemplateContentWithData($templateContent, $data);
            $xml = preg_replace('/<image([^>]*)>\K[\s\S]*?(?=<\/image>)/', '[IMAGE]', $xml);

            return $this->xmlToHtml($xml);
        } catch (\Throwable $e) {
            return '<p class="text-red-600 dark:text-red-400">Preview failed: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</p>';
        }
    }

    /**
     * Sample data for template preview (no real Receipt). Keys match Mustache template variables.
     *
     * @return array<string, mixed>
     */
    public function getSampleReceiptData(string $templateType): array
    {
        $items = [
            ['formatted_line' => 'Sample product 1        2 x 49,00', 'description_line' => null],
            ['formatted_line' => 'Another item            1 x 129,00', 'description_line' => null],
        ];
        $vatBreakdown = [
            ['vat_rate' => 25.0, 'vat_base_formatted' => '142,40', 'vat_amount_formatted' => '35,60'],
        ];

        return [
            'store_name' => 'Sample Store',
            'organization_number' => '123 456 789 MVA',
            'store_address' => 'Sample Street 1, 0123 Oslo',
            'store_logo_base64' => null,
            'store_logo_width' => null,
            'store_logo_height' => null,
            'session_number' => '001',
            'cashier_name' => 'Preview User',
            'transaction_id' => 'ch_preview_123',
            'order_number' => '1001',
            'receipt_number' => '1-S-000001',
            'date_time' => now()->format('d.m.Y H:i'),
            'items' => $items,
            'total_amount' => '227,00',
            'currency' => 'NOK',
            'vat_rate' => '25',
            'vat_base' => '142,40',
            'vat_amount' => '35,60',
            'vat_breakdown' => $vatBreakdown,
            'payment_method_display' => 'Kort',
            'is_split_payment' => false,
            'payments' => [],
            'terminal_number' => null,
            'card_brand' => null,
            'card_last4' => null,
            'tip_amount' => null,
            'original_receipt_number' => null,
            'customer_name' => 'Sample Customer',
            'customer_phone' => '123 45 678',
            'customer_email' => 'customer@example.com',
            'estimated_pickup_date' => null,
        ];
    }

    /**
     * Render template XML content with given data (Mustache + sanitize).
     */
    public function renderTemplateContentWithData(string $templateContent, array $data): string
    {
        $xml = $this->mustache->render($templateContent, $data);

        return $this->sanitizeXml($xml);
    }

    /**
     * Convert Epson ePOS XML string to HTML for on-screen preview.
     */
    protected function xmlToHtml(string $xml): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        if (@$dom->loadXML($xml) === false) {
            return '<p class="text-red-600 dark:text-red-400">Unable to parse receipt XML for preview.</p>';
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('epos', 'http://www.epson-pos.com/schemas/2011/03/epos-print');
        $xpath->registerNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');

        $eposPrint = $xpath->query('//epos:epos-print')->item(0);
        if (! $eposPrint) {
            return '<p class="text-red-600 dark:text-red-400">Receipt content not found in XML.</p>';
        }

        $lines = [];
        foreach ($eposPrint->childNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $localName = $node->localName ?? $node->nodeName;
            switch (strtolower($localName)) {
                case 'text':
                    $text = trim($node->textContent ?? '');
                    if ($text !== '') {
                        $lines[] = '<div class="receipt-line">'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</div>';
                    }
                    break;
                case 'feed':
                    $lines[] = '<br>';
                    break;
                case 'image':
                    $lines[] = '<div class="receipt-image-placeholder">[Logo]</div>';
                    break;
                case 'logo':
                    $lines[] = '<div class="receipt-image-placeholder">[Logo]</div>';
                    break;
                case 'barcode':
                    $code = trim($node->textContent ?? '');
                    if ($code !== '') {
                        $lines[] = '<div class="receipt-barcode">'.htmlspecialchars($code, ENT_QUOTES, 'UTF-8').'</div>';
                    }
                    $lines[] = '<br>';
                    break;
                case 'cut':
                    break;
                default:
                    break;
            }
        }

        $html = implode("\n", $lines);

        return '<div class="receipt-preview-paper">'.$html.'</div>';
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
            function ($matches) {
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
                    $attrString .= ' '.$key.'="'.htmlspecialchars($value, ENT_XML1, 'UTF-8').'"';
                }

                return '<text'.$attrString.'>'.$matches[3].'</text>';
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

                        if (! $hasContent) {
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
                                            if (! $next->hasAttribute($attr->nodeName)) {
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
                        ! $textElement->hasAttribute('font') &&
                        ! $textElement->hasAttribute('width') &&
                        ! $textElement->hasAttribute('height')) {
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
                if (! preg_match('/^<\?xml/', $xml)) {
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
            \Log::warning('XML sanitization DOM processing failed: '.$e->getMessage());

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
        $templatePath = $this->templatePath.'/'.$templateName;

        if (! File::exists($templatePath)) {
            throw new \RuntimeException("Receipt template not found: {$templatePath}");
        }

        return File::get($templatePath);
    }

    /**
     * Get template filename for receipt type
     */
    protected function getTemplateName(string $receiptType): string
    {
        return match ($receiptType) {
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
     * Build VAT breakdown per rate from charge metadata items (and optional total_tax fallback).
     *
     * @param  array<int, array<string, mixed>>  $rawItems  Items from charge metadata (price_amount/discount_amount in øre)
     * @param  float  $totalAmountNok  Total receipt amount in NOK
     * @param  array<string, mixed>  $chargeMetadata  Charge metadata (total_tax in NOK, etc.)
     * @return array<int, array{vat_rate: float, vat_base: float, vat_amount: float, vat_base_formatted: string, vat_amount_formatted: string}>
     */
    protected function buildVatBreakdownFromItems(array $rawItems, float $totalAmountNok, array $chargeMetadata): array
    {
        $byRate = [];

        foreach ($rawItems as $raw) {
            $qty = isset($raw['quantity']) && (int) $raw['quantity'] > 0 ? (int) $raw['quantity'] : 1;
            // Charge metadata may use price_amount (øre) or unit_price (øre per unit)
            $priceOre = isset($raw['price_amount']) ? (int) $raw['price_amount'] : 0;
            if ($priceOre === 0 && isset($raw['unit_price'])) {
                $priceOre = (int) $raw['unit_price'];
            }
            $discountOre = isset($raw['discount_amount']) ? (int) $raw['discount_amount'] : 0;
            $lineTotalOre = $priceOre * $qty - $discountOre;
            if ($lineTotalOre <= 0) {
                continue;
            }

            $rate = null;
            if (isset($raw['tax_rate']) && is_numeric($raw['tax_rate'])) {
                $rate = (float) $raw['tax_rate'];
            }
            if ($rate === null && ! empty($raw['article_group_code'])) {
                $agc = \App\Models\ArticleGroupCode::where('code', $raw['article_group_code'])->first();
                if ($agc !== null && $agc->default_vat_percent !== null) {
                    $rate = (float) $agc->default_vat_percent;
                }
            }
            if ($rate === null) {
                $rate = 25.0;
            }
            // Normalize: DB may store 0.15 / 0.25 (decimal); we use percentage (15, 25) everywhere
            if ($rate > 0 && $rate <= 1) {
                $rate = $rate * 100;
            }

            $key = (string) round($rate * 100);
            if (! isset($byRate[$key])) {
                $byRate[$key] = ['rate' => $rate, 'base' => 0.0, 'tax' => 0.0];
            }

            $lineTotalNok = $lineTotalOre / 100;
            $base = round($lineTotalNok / (1 + $rate / 100), 2);
            $tax = round($lineTotalNok - $base, 2);
            $byRate[$key]['base'] += $base;
            $byRate[$key]['tax'] += $tax;
        }

        if ($byRate !== []) {
            $out = [];
            foreach ($byRate as $entry) {
                $out[] = [
                    'vat_rate' => $entry['rate'],
                    'vat_base' => round($entry['base'], 2),
                    'vat_amount' => round($entry['tax'], 2),
                    'vat_base_formatted' => number_format($entry['base'], 2, ',', ' '),
                    'vat_amount_formatted' => number_format($entry['tax'], 2, ',', ' '),
                ];
            }
            usort($out, fn ($a, $b) => $b['vat_rate'] <=> $a['vat_rate']);

            return $out;
        }

        // No items: use total_tax from metadata when present and > 0, otherwise single 25% row
        $totalTaxNok = isset($chargeMetadata['total_tax']) && is_numeric($chargeMetadata['total_tax'])
            ? (float) $chargeMetadata['total_tax']
            : null;
        if ($totalTaxNok !== null && $totalTaxNok > 0 && $totalAmountNok > 0) {
            $vatBase = round($totalAmountNok - $totalTaxNok, 2);
            $vatAmount = round($totalTaxNok, 2);
            $vatRate = $vatBase > 0 ? round(($vatAmount / $vatBase) * 100, 2) : 25.0;
        } else {
            $vatRate = 25.0;
            $vatBase = round($totalAmountNok / (1 + 0.25), 2);
            $vatAmount = round($totalAmountNok - $vatBase, 2);
        }

        return [
            [
                'vat_rate' => $vatRate,
                'vat_base' => $vatBase,
                'vat_amount' => $vatAmount,
                'vat_base_formatted' => number_format($vatBase, 2, ',', ' '),
                'vat_amount_formatted' => number_format($vatAmount, 2, ',', ' '),
            ],
        ];
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

        // Get organization number from store model or metadata (prefer model field)
        $organizationNumber = $store->organisasjonsnummer ?? ($storeMetadata['organization_number'] ?? '');

        $totalAmount = $charge ? ($charge->amount / 100) : ($receiptData['total'] ?? 0);
        $chargeMetadata = $charge && is_array($charge->metadata) ? $charge->metadata : ($charge ? (array) json_decode($charge->metadata ?? '{}', true) : []);
        $rawItems = isset($chargeMetadata['items']) && is_array($chargeMetadata['items']) ? $chargeMetadata['items'] : [];

        // Build VAT breakdown per rate (so receipt shows 25%, 15%, 0% etc. separately)
        $vatBreakdown = $this->buildVatBreakdownFromItems($rawItems, $totalAmount, $chargeMetadata);
        $first = $vatBreakdown[0] ?? null;
        $vatRate = $first ? $first['vat_rate'] : 25.0;
        $vatBase = $first ? $first['vat_base'] : round($totalAmount / (1 + 0.25), 2);
        $vatAmount = $first ? $first['vat_amount'] : round($totalAmount - $vatBase, 2);

        // Prepare items and enrich with product information
        $items = [];
        if ($charge && isset($receiptData['items']) && ! empty($receiptData['items'])) {
            $items = $receiptData['items'];

            // Normalize and enrich items with product information
            foreach ($items as &$item) {
                // Ensure product name is set (don't replace with description)
                if (empty($item['name'])) {
                    // Get name from product if missing
                    if (isset($item['variant_id']) && $item['variant_id']) {
                        $variant = \App\Models\ProductVariant::find($item['variant_id']);
                        if ($variant && $variant->product) {
                            $item['name'] = $variant->product->name;
                            if ($variant->variant_name !== 'Default') {
                                $item['name'] .= ' - '.$variant->variant_name;
                            }
                        }
                    } elseif (isset($item['product_id']) && $item['product_id']) {
                        $product = \App\Models\ConnectedProduct::find($item['product_id']);
                        if ($product) {
                            $item['name'] = $product->name;
                        }
                    }
                    // Final fallback - use product_name or 'Vare'
                    if (empty($item['name'])) {
                        $item['name'] = $item['product_name'] ?? 'Vare';
                    }
                }

                // Preserve description separately (don't overwrite name with description)
                // Description will be shown on a separate line if it exists
                if (empty($item['description']) && ! empty($item['product_name'])) {
                    // If description is not set but we have product_name, description stays empty
                    // (we'll show only product name)
                }

                // Ensure quantity is present
                if (! isset($item['quantity']) || $item['quantity'] <= 0) {
                    $item['quantity'] = 1;
                }

                // Check if unit_price is already a formatted string (contains comma)
                $unitPriceFormatted = isset($item['unit_price']) && is_string($item['unit_price']) && strpos($item['unit_price'], ',') !== false;

                // Calculate and format unit_price if not already formatted
                if (! $unitPriceFormatted) {
                    $unitPrice = 0;
                    if (isset($item['price_amount'])) {
                        // price_amount is always in cents
                        $unitPrice = ((int) $item['price_amount']) / 100;
                    } elseif (isset($item['line_total_amount'])) {
                        // line_total_amount is always in cents
                        $unitPrice = ((int) $item['line_total_amount']) / 100 / $item['quantity'];
                    } elseif (isset($item['unit_price']) && is_numeric($item['unit_price'])) {
                        $unitPrice = (float) $item['unit_price'];
                        // If value is large integer (> 100), it's likely in cents
                        if ($unitPrice > 100 && $unitPrice == (int) $unitPrice) {
                            $unitPrice = $unitPrice / 100;
                        }
                    } elseif (isset($item['line_total']) && is_numeric($item['line_total']) && (! is_string($item['line_total']) || strpos($item['line_total'], ',') === false)) {
                        $lineTotal = (float) $item['line_total'];
                        // If value is large integer (> 100), it's likely in cents
                        if ($lineTotal > 100 && $lineTotal == (int) $lineTotal) {
                            $lineTotal = $lineTotal / 100;
                        }
                        $unitPrice = $lineTotal / $item['quantity'];
                    }
                    // Only set if we have a valid price > 0
                    if ($unitPrice > 0) {
                        $item['unit_price'] = number_format($unitPrice, 2, ',', ' ');
                    }
                }

                // Check if line_total is already a formatted string (contains comma)
                $lineTotalFormatted = isset($item['line_total']) && is_string($item['line_total']) && strpos($item['line_total'], ',') !== false;

                // Calculate and format line_total if not already formatted
                if (! $lineTotalFormatted) {
                    $lineTotal = 0;
                    if (isset($item['line_total_amount'])) {
                        // line_total_amount is always in cents
                        $lineTotal = ((int) $item['line_total_amount']) / 100;
                    } elseif (isset($item['price_amount'])) {
                        // price_amount is always in cents
                        $lineTotal = ((int) $item['price_amount']) / 100 * $item['quantity'];
                    } elseif (isset($item['line_total']) && is_numeric($item['line_total']) && (! is_string($item['line_total']) || strpos($item['line_total'], ',') === false)) {
                        $lineTotal = (float) $item['line_total'];
                        // If value is large integer (> 100), it's likely in cents
                        if ($lineTotal > 100 && $lineTotal == (int) $lineTotal) {
                            $lineTotal = $lineTotal / 100;
                        }
                    } elseif (isset($item['unit_price'])) {
                        // Check if unit_price is already formatted
                        if (is_string($item['unit_price']) && strpos($item['unit_price'], ',') !== false) {
                            // Already formatted, extract numeric value
                            $unitPriceStr = str_replace([' ', ','], ['', '.'], $item['unit_price']);
                            $unitPrice = (float) $unitPriceStr;
                            $lineTotal = $unitPrice * $item['quantity'];
                        } elseif (is_numeric($item['unit_price']) && (! is_string($item['unit_price']) || strpos($item['unit_price'], ',') === false)) {
                            $unitPrice = (float) $item['unit_price'];
                            // If value is large integer (> 100), it's likely in cents
                            if ($unitPrice > 100 && $unitPrice == (int) $unitPrice) {
                                $unitPrice = $unitPrice / 100;
                            }
                            $lineTotal = $unitPrice * $item['quantity'];
                        }
                    }
                    // Only set if we have a valid total > 0
                    if ($lineTotal > 0) {
                        $item['line_total'] = number_format($lineTotal, 2, ',', ' ');
                    }
                }

                // Enrich with product information (SKU/barcode) if variant_id is available
                if (isset($item['variant_id']) && $item['variant_id']) {
                    $variant = \App\Models\ProductVariant::find($item['variant_id']);
                    if ($variant) {
                        // Add SKU or barcode if available
                        if ($variant->sku && ! isset($item['sku'])) {
                            $item['sku'] = $variant->sku;
                        }
                        if ($variant->barcode && ! isset($item['barcode'])) {
                            $item['barcode'] = $variant->barcode;
                        }
                        // Use product code (SKU or barcode) for display
                        $item['product_code'] = $variant->sku ?? $variant->barcode ?? null;
                    }
                } elseif (isset($item['product_id']) && $item['product_id']) {
                    // Try to get first variant's SKU/barcode if no variant_id
                    $product = \App\Models\ConnectedProduct::find($item['product_id']);
                    if ($product && $product->variants()->count() > 0) {
                        $variant = $product->variants()->first();
                        if ($variant) {
                            $item['product_code'] = $variant->sku ?? $variant->barcode ?? null;
                        }
                    }
                }

                // Format the entire line to prevent overflow
                // For 80mm receipts: max ~48 characters per line
                // Format: "Product Name        Qty x Price" (no line total to allow longer names)

                $quantityStr = (string) ($item['quantity'] ?? 1);
                $unitPriceStr = $item['unit_price'] ?? '0,00';

                // Check if this is a return receipt
                $isReturn = $receipt->receipt_type === 'return';

                // Build price section: "Qty x Price" (with negative sign for returns)
                if ($isReturn) {
                    // Ensure negative sign is present (remove if already there to avoid double negative)
                    $unitPriceStr = ltrim($unitPriceStr, '-');
                    $priceSection = sprintf('%s x -%s', $quantityStr, $unitPriceStr);
                } else {
                    $priceSection = sprintf('%s x %s', $quantityStr, $unitPriceStr);
                }

                $priceSectionLength = mb_strlen($priceSection);

                // Calculate max name length (48 chars total - price section - spacing)
                // Reserve 8 spaces for padding between name and price
                $maxNameLength = 48 - $priceSectionLength - 8;
                if ($maxNameLength < 10) {
                    $maxNameLength = 10; // Minimum name length
                }

                // Truncate name if needed
                $productName = $item['name'] ?? 'Vare';
                if (mb_strlen($productName) > $maxNameLength) {
                    $productName = mb_substr($productName, 0, $maxNameLength - 3).'...';
                }

                // Format the complete line with proper spacing
                // Pad name to fixed width, then add price section
                $item['formatted_line'] = sprintf('%-'.$maxNameLength.'s        %s', $productName, $priceSection);

                // If description exists and is different from product name, add it as a separate line
                $description = $item['description'] ?? null;
                if (! empty($description) && $description !== $productName && $description !== $item['product_name']) {
                    // Truncate description if too long (leave room for tab indent)
                    $maxDescLength = 48 - 4; // 4 chars for tab indent
                    $descriptionText = $description;
                    if (mb_strlen($descriptionText) > $maxDescLength) {
                        $descriptionText = mb_substr($descriptionText, 0, $maxDescLength - 3).'...';
                    }
                    // Store description line with tab indent (4 spaces)
                    $item['description_line'] = '    '.$descriptionText;
                } else {
                    $item['description_line'] = null;
                }

                // Also keep the original name (truncated) for backwards compatibility
                $item['name'] = $productName;
            }
            unset($item); // Break reference
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
        $isSplitPayment = $receiptData['is_split_payment'] ?? false;
        $storeId = $store->id;

        if ($isSplitPayment && isset($receiptData['payments'])) {
            $paymentMethods = [];
            foreach ($receiptData['payments'] as $payment) {
                $paymentMethods[] = $this->formatPaymentMethod($payment['method'] ?? 'unknown', $storeId).
                    ' '.number_format($payment['amount'], 2, ',', ' ').' kr';
            }
            $paymentMethodDisplay = implode(' + ', $paymentMethods);
        } else {
            $paymentMethodDisplay = $this->formatPaymentMethod($charge?->payment_method ?? 'unknown', $storeId);
        }

        // Format date/time in Oslo timezone (order/transaction time, not print time)
        // Use date from receipt_data if available (from charge paid_at / created_at), otherwise use receipt created_at
        if (isset($receiptData['date'])) {
            // Parse the date string (already in Oslo timezone from ReceiptGenerationService)
            $dateTime = \Carbon\Carbon::parse($receiptData['date'], 'Europe/Oslo')
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

        // Get customer information from receipt_data (already populated by ReceiptGenerationService)
        $customerName = $receiptData['customer_name'] ?? null;
        $customerPhone = $receiptData['customer_phone'] ?? null;
        $customerEmail = $receiptData['customer_email'] ?? null;

        // Get estimated pickup date from receipt_data (for delivery receipts)
        $estimatedPickupDate = $receiptData['estimated_pickup_date'] ?? null;

        // Get order number from receipt_data or use charge ID (purchase database ID)
        $orderNumber = $receiptData['order_number'] ?? null;
        if (! $orderNumber && $charge) {
            $orderNumber = (string) $charge->id;
        }

        // Get store logo as ePOS base64 raster (required by ePOS-Print XML; see Epson manual)
        // Cache the raster output keyed by logo path and modification time
        $storeLogoBase64 = null;
        $storeLogoWidth = null;
        $storeLogoHeight = null;
        if ($store->logo_path && Storage::disk('public')->exists($store->logo_path)) {
            $logoMtime = Storage::disk('public')->lastModified($store->logo_path);
            $cacheKey = 'epos_logo_raster:'.md5($store->logo_path.':'.$logoMtime);
            $raster = Cache::remember($cacheKey, now()->addDays(7), function () use ($store) {
                $logoBlob = Storage::disk('public')->get($store->logo_path);

                return $this->convertImageToEposRaster($logoBlob, 576);
            });
            if ($raster !== null) {
                $storeLogoBase64 = $raster['base64'];
                $storeLogoWidth = $raster['width'];
                $storeLogoHeight = $raster['height'];
            }
        }

        return [
            'store_name' => $store->name,
            'organization_number' => $organizationNumber,
            'store_address' => $store->address ?? ($storeMetadata['address'] ?? ''),
            'store_logo_base64' => $storeLogoBase64,
            'store_logo_width' => $storeLogoWidth,
            'store_logo_height' => $storeLogoHeight,
            'session_number' => $session?->session_number ?? 'N/A',
            'cashier_name' => $user?->name ?? 'N/A',
            'transaction_id' => $charge?->stripe_charge_id ?? $receiptData['transaction_id'] ?? 'N/A',
            'order_number' => $orderNumber,
            'receipt_number' => $receipt->receipt_number,
            'date_time' => $dateTime,
            'items' => $items,
            'total_amount' => number_format($totalAmount, 2, ',', ' '),
            'currency' => strtoupper($charge?->currency ?? 'NOK'),
            'vat_rate' => (string) $vatRate,
            'vat_base' => number_format($vatBase, 2, ',', ' '),
            'vat_amount' => number_format($vatAmount, 2, ',', ' '),
            'vat_breakdown' => $vatBreakdown,
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
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'customer_email' => $customerEmail,
            'estimated_pickup_date' => $estimatedPickupDate,
        ];
    }

    /**
     * Flatten image onto white background so transparent pixels become white (not black).
     * Prevents transparent PNG/WebP logos from rendering as a black box on thermal receipts.
     * GD alpha: 0 = opaque, 127 = fully transparent.
     */
    protected function flattenImageOntoWhite(\GdImage $src, int $width, int $height): ?\GdImage
    {
        $white = imagecreatetruecolor($width, $height);
        if ($white === false) {
            return $src;
        }
        $whiteColor = imagecolorallocate($white, 255, 255, 255);
        if ($whiteColor === false) {
            imagedestroy($white);

            return $src;
        }
        imagefill($white, 0, 0, $whiteColor);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($src, $x, $y);
                if ($color === false) {
                    continue;
                }
                $alpha = ($color >> 24) & 0x7F;
                if ($alpha >= 64) {
                    imagesetpixel($white, $x, $y, $whiteColor);
                } else {
                    $r = ($color >> 16) & 0xFF;
                    $g = ($color >> 8) & 0xFF;
                    $b = $color & 0xFF;
                    $c = imagecolorallocate($white, $r, $g, $b);
                    if ($c !== false) {
                        imagesetpixel($white, $x, $y, $c);
                    }
                }
            }
        }
        imagedestroy($src);

        return $white;
    }

    /**
     * Convert image binary to ePOS-Print raster (1-bit, base64).
     * See Epson ePOS-Print XML User's Manual: <image> expects base64Binary raster data.
     *
     * @param  string  $imageData  Raw image bytes (JPEG, PNG, WebP, GIF)
     * @param  int  $maxWidthDots  Max width in dots (e.g. 576 for 80mm at 203dpi)
     * @return array{base64: string, width: int, height: int}|null
     */
    protected function convertImageToEposRaster(string $imageData, int $maxWidthDots = 576): ?array
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $src = @imagecreatefromstring($imageData);
        if ($src === false) {
            return null;
        }

        // Convert palette-based images (GIF, palette PNG) to truecolor.
        // imagecolorat() returns a palette index for palette images, not packed ARGB,
        // which causes flattenImageOntoWhite to produce garbled output.
        if (! imageistruecolor($src)) {
            imagepalettetotruecolor($src);
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($src);

            return null;
        }

        $src = $this->flattenImageOntoWhite($src, $srcW, $srcH);
        if ($src === null) {
            return null;
        }

        $widthDots = min($srcW, $maxWidthDots);
        $widthDots = (int) (ceil($widthDots / 8) * 8);
        $scale = $widthDots / $srcW;
        $heightDots = (int) round($srcH * $scale);
        if ($heightDots <= 0) {
            $heightDots = 1;
        }

        $dst = imagecreatetruecolor($widthDots, $heightDots);
        if ($dst === false) {
            imagedestroy($src);

            return null;
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $widthDots, $heightDots, $srcW, $srcH);
        imagedestroy($src);

        $bytesPerRow = (int) (ceil($widthDots / 8));
        $raster = '';
        for ($y = 0; $y < $heightDots; $y++) {
            for ($byteIndex = 0; $byteIndex < $bytesPerRow; $byteIndex++) {
                $byte = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $x = $byteIndex * 8 + $bit;
                    if ($x < $widthDots) {
                        $rgb = @imagecolorat($dst, $x, $y);
                        $gray = $rgb !== false
                            ? (int) (0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF))
                            : 255;
                        if ($gray < 128) {
                            $byte |= (1 << (7 - $bit));
                        }
                    }
                }
                $raster .= chr($byte);
            }
        }
        imagedestroy($dst);

        return [
            'base64' => base64_encode($raster),
            'width' => $widthDots,
            'height' => $heightDots,
        ];
    }

    /**
     * Format payment method for display
     *
     * @param  string|null  $paymentMethodCode  Payment method code (e.g., 'cash', 'vipps', 'card')
     * @param  int|null  $storeId  Store ID to look up payment method name from database
     * @return string Display name for payment method
     */
    protected function formatPaymentMethod(?string $paymentMethodCode, ?int $storeId = null): string
    {
        if (! $paymentMethodCode) {
            return 'Ukjent';
        }

        // First check hardcoded mappings for common payment methods
        $hardcoded = match ($paymentMethodCode) {
            'cash' => 'Kontant',
            'card' => 'Kort',
            'credit_card' => 'Kredittkort',
            'mobile' => 'Mobil',
            'gift_token' => 'Gavekort',
            'customer_card' => 'Kundekort',
            'loyalty' => 'Lojalitetspoeng',
            'vipps' => 'Vipps',
            default => null,
        };

        if ($hardcoded !== null) {
            return $hardcoded;
        }

        // If not in hardcoded list and we have store ID, look up from database
        if ($storeId) {
            $paymentMethod = PaymentMethod::where('store_id', $storeId)
                ->where('code', $paymentMethodCode)
                ->first();

            if ($paymentMethod && $paymentMethod->name) {
                return $paymentMethod->name;
            }
        }

        // Fallback: return capitalized code or 'Ukjent'
        return ucfirst($paymentMethodCode) ?: 'Ukjent';
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
