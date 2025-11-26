<?php

namespace App\Actions\SafT;

use App\Models\Store;
use App\Models\PosSession;
use App\Models\PosSessionClosing;
use App\Models\ConnectedCharge;
use App\Models\PosEvent;
use App\Services\SafTCodeMapper;
use Illuminate\Support\Facades\Log;
use DOMDocument;
use DOMElement;

class GenerateSafTCashRegister
{
    /**
     * Generate SAF-T Cash Register XML file
     * Based on Norwegian_SAF-T_Cash_Register_Schema_v_1.00.xsd
     * 
     * @param Store $store
     * @param \DateTime|string $fromDate Start date (inclusive)
     * @param \DateTime|string $toDate End date (inclusive)
     * @return string XML content
     */
    public function __invoke(Store $store, $fromDate, $toDate): string
    {
        // Convert dates to DateTime if needed
        if (is_string($fromDate)) {
            $fromDate = new \DateTime($fromDate);
        }
        if (is_string($toDate)) {
            $toDate = new \DateTime($toDate);
        }

        // Create XML document
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Root element: AuditFile
        $auditFile = $xml->createElement('AuditFile');
        $xml->appendChild($auditFile);

        // Header
        $header = $xml->createElement('Header');
        $auditFile->appendChild($header);

        $this->addElement($xml, $header, 'AuditFileVersion', '1.00');
        $this->addElement($xml, $header, 'AuditFileCountry', 'NO');
        $this->addElement($xml, $header, 'AuditFileDateCreated', now()->format('Y-m-d\TH:i:s'));
        $this->addElement($xml, $header, 'SoftwareCompanyName', config('app.name', 'POS System'));
        $this->addElement($xml, $header, 'SoftwareID', config('app.name', 'POS System'));
        $this->addElement($xml, $header, 'SoftwareVersion', '1.0');
        $this->addElement($xml, $header, 'CompanyName', $store->name);
        // Get organization number from store metadata or config
        $orgNumber = '';
        if (method_exists($store, 'getAttribute') && $store->getAttribute('metadata')) {
            $metadata = is_array($store->metadata) ? $store->metadata : json_decode($store->metadata, true);
            $orgNumber = $metadata['organization_number'] ?? '';
        }
        $this->addElement($xml, $header, 'CompanyRegistrationNumber', $orgNumber);
        $this->addElement($xml, $header, 'TaxAccountingBasis', 'K'); // K = Kontantmetoden (Cash basis)
        $this->addElement($xml, $header, 'CurrencyCode', 'NOK');
        $this->addElement($xml, $header, 'DateCreated', now()->format('Y-m-d'));
        $this->addElement($xml, $header, 'TaxEntity', $store->name);
        $this->addElement($xml, $header, 'SelectionCriteria', "From: {$fromDate->format('Y-m-d')} To: {$toDate->format('Y-m-d')}");

        // MasterData
        $masterData = $xml->createElement('MasterData');
        $auditFile->appendChild($masterData);

        // CashRegister
        $cashRegister = $xml->createElement('CashRegister');
        $masterData->appendChild($cashRegister);

        $this->addElement($xml, $cashRegister, 'CashRegisterID', (string) $store->id);
        $this->addElement($xml, $cashRegister, 'CashRegisterDescription', $store->name);

        // Get all sessions in date range
        $sessions = PosSession::where('store_id', $store->id)
            ->whereDate('opened_at', '>=', $fromDate->format('Y-m-d'))
            ->whereDate('opened_at', '<=', $toDate->format('Y-m-d'))
            ->where('status', 'closed')
            ->with(['charges', 'posDevice', 'user', 'events'])
            ->orderBy('opened_at')
            ->get();

        // GeneralLedgerEntries
        $generalLedgerEntries = $xml->createElement('GeneralLedgerEntries');
        $auditFile->appendChild($generalLedgerEntries);

        $this->addElement($xml, $generalLedgerEntries, 'NumberOfEntries', (string) $sessions->count());
        $this->addElement($xml, $generalLedgerEntries, 'TotalDebit', (string) $this->calculateTotalDebit($sessions));
        $this->addElement($xml, $generalLedgerEntries, 'TotalCredit', (string) $this->calculateTotalCredit($sessions));

        // Process each session
        foreach ($sessions as $session) {
            $journal = $xml->createElement('Journal');
            $generalLedgerEntries->appendChild($journal);

            $this->addElement($xml, $journal, 'JournalID', $session->session_number);
            $this->addElement($xml, $journal, 'Description', "Kassesesjon {$session->session_number}");
            $this->addElement($xml, $journal, 'JournalType', 'KR'); // KR = Kasse (Cash Register)
            $this->addElement($xml, $journal, 'StartDate', $session->opened_at->format('Y-m-d'));
            $this->addElement($xml, $journal, 'EndDate', $session->closed_at?->format('Y-m-d') ?? $session->opened_at->format('Y-m-d'));

            // Transaction
            $transaction = $xml->createElement('Transaction');
            $journal->appendChild($transaction);

            $this->addElement($xml, $transaction, 'TransactionID', (string) $session->id);
            
            // Process charges (transactions) for this session
            $charges = $session->charges->where('status', 'succeeded');
            
            // Add TransactionCode (PredefinedBasicID-11) - use first charge's code or default
            $firstCharge = $charges->first();
            $transactionCode = $firstCharge?->transaction_code ?? '11001'; // Default to cash sale
            if ($transactionCode) {
                $this->addElement($xml, $transaction, 'TransactionCode', $transactionCode);
            }
            
            $this->addElement($xml, $transaction, 'Period', $session->opened_at->format('Y-m'));
            $this->addElement($xml, $transaction, 'TransactionDate', $session->opened_at->format('Y-m-d'));
            $this->addElement($xml, $transaction, 'SourceID', $session->posDevice?->device_name ?? 'Unknown');
            $this->addElement($xml, $transaction, 'Description', "Sesjon {$session->session_number}");
            
            foreach ($charges as $charge) {
                $line = $xml->createElement('Line');
                $transaction->appendChild($line);

                $this->addElement($xml, $line, 'RecordID', (string) $charge->id);
                $this->addElement($xml, $line, 'AccountID', $this->getAccountIdForPaymentMethod($charge->payment_method));
                
                // Add ArticleGroupCode (PredefinedBasicID-04)
                if ($charge->article_group_code) {
                    $this->addElement($xml, $line, 'ArticleGroupCode', $charge->article_group_code);
                }
                
                $this->addElement($xml, $line, 'SourceDocumentID', $charge->stripe_charge_id);
                $this->addElement($xml, $line, 'Description', $charge->description ?? "Salg {$charge->stripe_charge_id}");
                $this->addElement($xml, $line, 'DebitAmount', (string) $charge->amount);
                $this->addElement($xml, $line, 'CreditAmount', '0');
                $this->addElement($xml, $line, 'TransactionDate', $charge->paid_at?->format('Y-m-d') ?? $charge->created_at->format('Y-m-d'));

                // TaxInformation
                $taxInformation = $xml->createElement('TaxInformation');
                $line->appendChild($taxInformation);

                $this->addElement($xml, $taxInformation, 'TaxCode', $this->getTaxCode($charge));
                $this->addElement($xml, $taxInformation, 'TaxPercentage', $this->getTaxPercentage($charge));
                $this->addElement($xml, $taxInformation, 'TaxAmount', (string) $this->calculateTaxAmount($charge));
                
                // Add tip if present (PredefinedBasicID-10)
                if ($charge->tip_amount && $charge->tip_amount > 0) {
                    $tipLine = $xml->createElement('Line');
                    $transaction->appendChild($tipLine);
                    
                    $this->addElement($xml, $tipLine, 'RecordID', (string) ($charge->id . '-tip'));
                    $this->addElement($xml, $tipLine, 'AccountID', '3001'); // Tips account
                    $this->addElement($xml, $tipLine, 'RaiseCode', '10001'); // PredefinedBasicID-10
                    $this->addElement($xml, $tipLine, 'SourceDocumentID', $charge->stripe_charge_id);
                    $this->addElement($xml, $tipLine, 'Description', 'Drikkepenger/Tips');
                    $this->addElement($xml, $tipLine, 'DebitAmount', (string) $charge->tip_amount);
                    $this->addElement($xml, $tipLine, 'CreditAmount', '0');
                    $this->addElement($xml, $tipLine, 'TransactionDate', $charge->paid_at?->format('Y-m-d') ?? $charge->created_at->format('Y-m-d'));
                }

                // Credit line (revenue)
                $creditLine = $xml->createElement('Line');
                $transaction->appendChild($creditLine);

                $this->addElement($xml, $creditLine, 'RecordID', (string) ($charge->id . '-credit'));
                $this->addElement($xml, $creditLine, 'AccountID', '3000'); // Revenue account
                $this->addElement($xml, $creditLine, 'SourceDocumentID', $charge->stripe_charge_id);
                $this->addElement($xml, $creditLine, 'Description', $charge->description ?? "Salg {$charge->stripe_charge_id}");
                $this->addElement($xml, $creditLine, 'DebitAmount', '0');
                $this->addElement($xml, $creditLine, 'CreditAmount', (string) $charge->amount);
                $this->addElement($xml, $creditLine, 'TransactionDate', $charge->paid_at?->format('Y-m-d') ?? $charge->created_at->format('Y-m-d'));
            }
            
            // Add events for this session
            $sessionEvents = $session->events ?? collect();
            if ($sessionEvents->isNotEmpty()) {
                $eventsElement = $xml->createElement('Events');
                $journal->appendChild($eventsElement);
                
                foreach ($sessionEvents as $event) {
                    $eventElement = $xml->createElement('Event');
                    $eventsElement->appendChild($eventElement);
                    
                    $this->addElement($xml, $eventElement, 'EventCode', $event->event_code);
                    $this->addElement($xml, $eventElement, 'EventType', $event->event_type);
                    $this->addElement($xml, $eventElement, 'Description', $event->description ?? $event->event_description);
                    $this->addElement($xml, $eventElement, 'OccurredAt', $event->occurred_at->format('Y-m-d\TH:i:s'));
                    
                    if ($event->event_data) {
                        $eventDataElement = $xml->createElement('EventData');
                        $eventElement->appendChild($eventDataElement);
                        foreach ($event->event_data as $key => $value) {
                            $this->addElement($xml, $eventDataElement, $key, (string) $value);
                        }
                    }
                }
            }
        }

        return $xml->saveXML();
    }

    /**
     * Add element to parent
     */
    protected function addElement(DOMDocument $xml, DOMElement $parent, string $name, string $value): void
    {
        $element = $xml->createElement($name, htmlspecialchars($value, ENT_XML1, 'UTF-8'));
        $parent->appendChild($element);
    }

    /**
     * Get account ID for payment method
     */
    protected function getAccountIdForPaymentMethod(?string $paymentMethod): string
    {
        return match($paymentMethod) {
            'cash' => '1920', // Cash
            'card' => '1921', // Card payments
            default => '1922', // Other payment methods
        };
    }

    /**
     * Get tax code for charge
     */
    protected function getTaxCode(ConnectedCharge $charge): string
    {
        // Default to standard VAT rate (25% in Norway)
        // This should be configurable per product/store
        return '1'; // Standard VAT rate
    }

    /**
     * Get tax percentage for charge
     */
    protected function getTaxPercentage(ConnectedCharge $charge): string
    {
        // Default to 25% VAT (standard rate in Norway)
        // This should be calculated based on product tax settings
        return '25.00';
    }

    /**
     * Calculate tax amount
     */
    protected function calculateTaxAmount(ConnectedCharge $charge): int
    {
        // Calculate VAT amount (25% of total, so 20% of net)
        // This is a simplified calculation - should use actual tax rates
        $taxRate = 0.25;
        return (int) round($charge->amount * $taxRate / (1 + $taxRate));
    }

    /**
     * Calculate total debit
     */
    protected function calculateTotalDebit($sessions): int
    {
        $total = 0;
        foreach ($sessions as $session) {
            $total += $session->charges->where('status', 'succeeded')->sum('amount');
        }
        return $total;
    }

    /**
     * Calculate total credit
     */
    protected function calculateTotalCredit($sessions): int
    {
        // Credit equals debit in double-entry bookkeeping
        return $this->calculateTotalDebit($sessions);
    }
}

