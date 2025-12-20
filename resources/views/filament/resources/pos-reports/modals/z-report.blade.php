<style>
    .z-report-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    .z-report-section {
        padding: 1.25rem;
        border-radius: 0.5rem;
        border-width: 1px;
    }
    .z-report-header {
        background-color: rgb(240 253 244);
        border-color: rgb(187 247 208);
    }
    .dark .z-report-header {
        background-color: rgba(20, 83, 45, 0.2);
        border-color: rgb(20 83 45);
    }
    .z-report-card {
        background-color: white;
        border-color: rgb(229 231 235);
    }
    .dark .z-report-card {
        background-color: rgb(31 41 55);
        border-color: rgb(55 65 81);
    }
    .z-report-metric-label {
        font-size: 0.875rem;
        color: rgb(107 114 128);
        margin-bottom: 0.5rem;
    }
    .dark .z-report-metric-label {
        color: rgb(156 163 175);
    }
    .z-report-metric-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }
    .dark .z-report-metric-value {
        color: white;
    }
    .z-report-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: rgb(17 24 39);
    }
    .dark .z-report-title {
        color: white;
    }
    .z-report-grid {
        display: grid;
        gap: 1rem;
    }
    .z-report-grid-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .z-report-grid-3 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
    .z-report-grid-4 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .z-report-grid-5 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    @media (min-width: 768px) {
        .z-report-grid-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .z-report-grid-4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        .z-report-grid-5 {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }
    }
    .z-report-table {
        width: 100%;
        font-size: 0.875rem;
    }
    .z-report-table thead {
        background-color: rgb(249 250 251);
        border-bottom-width: 1px;
        border-color: rgb(229 231 235);
    }
    .dark .z-report-table thead {
        background-color: rgb(17 24 39);
        border-color: rgb(55 65 81);
    }
    .z-report-table th {
        padding: 0.75rem;
        text-align: left;
        font-weight: 600;
        color: rgb(55 65 81);
    }
    .dark .z-report-table th {
        color: rgb(209 213 219);
    }
    .z-report-table td {
        padding: 0.75rem;
        border-bottom-width: 1px;
        border-color: rgb(243 244 246);
        color: rgb(75 85 99);
    }
    .dark .z-report-table td {
        border-color: rgb(31 41 55);
        color: rgb(156 163 175);
    }
    .z-report-table tbody tr:hover {
        background-color: rgb(249 250 251);
    }
    .dark .z-report-table tbody tr:hover {
        background-color: rgb(17 24 39);
    }
    .z-report-sticky-header {
        position: sticky;
        top: 0;
        background-color: rgb(249 250 251);
        z-index: 10;
    }
    .dark .z-report-sticky-header {
        background-color: rgb(17 24 39);
    }
    .z-report-scrollable {
        max-height: 24rem;
        overflow-y: auto;
    }
</style>

<div class="z-report-container">
    <!-- Download Button -->
    <div style="margin-bottom: 1rem; text-align: right;">
        <a href="{{ route('reports.z-report.pdf', ['tenant' => $session->store->slug, 'sessionId' => $session->id]) }}" 
           target="_blank"
           style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background-color: rgb(239 68 68); color: white; text-decoration: none; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500;">
            <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Last ned PDF
        </a>
    </div>
    
    <!-- Header Section -->
    <div class="z-report-section z-report-header">
        <h3 class="z-report-title">Z-Rapport (Sluttrapport)</h3>
        <div class="z-report-grid z-report-grid-4" style="font-size: 0.875rem;">
            <div>
                <div class="z-report-metric-label">Øktsnummer</div>
                <div style="font-weight: 600; color: rgb(17 24 39);">{{ $session->session_number }}</div>
            </div>
            <div>
                <div class="z-report-metric-label">Butikk</div>
                <div style="font-weight: 600; color: rgb(17 24 39);">{{ $report['store']['name'] ?? 'N/A' }}</div>
            </div>
            <div>
                <div class="z-report-metric-label">Åpnet</div>
                <div style="font-weight: 600; color: rgb(17 24 39);">{{ $session->opened_at->format('d.m.Y H:i') }}</div>
            </div>
            <div>
                <div class="z-report-metric-label">Stengt</div>
                <div style="font-weight: 600; color: rgb(17 24 39);">{{ $session->closed_at?->format('d.m.Y H:i') ?? 'N/A' }}</div>
            </div>
            @if($report['device'])
                <div>
                    <div class="z-report-metric-label">Enhet</div>
                    <div style="font-weight: 600; color: rgb(17 24 39);">{{ $report['device']['name'] }}</div>
                </div>
            @endif
            @if($report['cashier'])
                <div>
                    <div class="z-report-metric-label">Kasserer</div>
                    <div style="font-weight: 600; color: rgb(17 24 39);">{{ $report['cashier']['name'] }}</div>
                </div>
            @endif
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="z-report-grid z-report-grid-4">
        <div class="z-report-section z-report-card">
            <div class="z-report-metric-label">Transaksjoner</div>
            <div class="z-report-metric-value">{{ $report['transactions_count'] }}</div>
        </div>
        <div class="z-report-section z-report-card">
            <div class="z-report-metric-label">Totalt Beløp</div>
            <div class="z-report-metric-value">{{ number_format($report['total_amount'] / 100, 2) }} NOK</div>
        </div>
        <div class="z-report-section z-report-card">
            <div class="z-report-metric-label">Kontant</div>
            <div class="z-report-metric-value">{{ number_format($report['cash_amount'] / 100, 2) }} NOK</div>
        </div>
        <div class="z-report-section z-report-card">
            <div class="z-report-metric-label">Kort</div>
            <div class="z-report-metric-value">{{ number_format($report['card_amount'] / 100, 2) }} NOK</div>
        </div>
    </div>

    @if($report['mobile_amount'] > 0 || $report['other_amount'] > 0)
        <div class="z-report-grid z-report-grid-2">
            @if($report['mobile_amount'] > 0)
                <div class="z-report-section z-report-card">
                    <div class="z-report-metric-label">Mobil</div>
                    <div class="z-report-metric-value" style="font-size: 1.25rem;">{{ number_format($report['mobile_amount'] / 100, 2) }} NOK</div>
                </div>
            @endif
            @if($report['other_amount'] > 0)
                <div class="z-report-section z-report-card">
                    <div class="z-report-metric-label">Annet</div>
                    <div class="z-report-metric-value" style="font-size: 1.25rem;">{{ number_format($report['other_amount'] / 100, 2) }} NOK</div>
                </div>
            @endif
        </div>
    @endif

    <!-- Cash Management -->
    <div class="z-report-grid {{ (!empty($report['tips_enabled']) && $report['tips_enabled'] === true) ? 'z-report-grid-5' : 'z-report-grid-4' }}">
        <div class="z-report-section" style="background-color: rgb(254 252 232); border-color: rgb(253 224 71);">
            <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                <strong>Åpningssaldo</strong>
            </div>
            <div style="font-size: 1.25rem; font-weight: 700; color: rgb(17 24 39);">{{ number_format($report['opening_balance'] ?? 0, 2) }} NOK</div>
        </div>
        <div class="z-report-section" style="background-color: rgb(254 252 232); border-color: rgb(253 224 71);">
            <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                <strong>Forventet Kontant</strong>
            </div>
            <div style="font-size: 1.25rem; font-weight: 700; color: rgb(17 24 39);">{{ number_format($report['expected_cash'], 2) }} NOK</div>
        </div>
        <div class="z-report-section" style="background-color: rgb(250 245 255); border-color: rgb(233 213 255);">
            <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                <strong>Faktisk Kontant</strong>
            </div>
            <div style="font-size: 1.25rem; font-weight: 700; color: rgb(17 24 39);">{{ number_format($report['actual_cash'] ?? 0, 2) }} NOK</div>
        </div>
        <div class="z-report-section" style="background-color: {{ ($report['cash_difference'] ?? 0) > 0 ? 'rgb(254 242 242)' : (($report['cash_difference'] ?? 0) < 0 ? 'rgb(254 252 232)' : 'rgb(240 253 244)'); }}; border-color: {{ ($report['cash_difference'] ?? 0) > 0 ? 'rgb(252 165 165)' : (($report['cash_difference'] ?? 0) < 0 ? 'rgb(253 224 71)' : 'rgb(187 247 208)'); }};">
            <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                <strong>Differanse</strong>
            </div>
            <div style="font-size: 1.25rem; font-weight: 700; color: rgb(17 24 39);">{{ number_format($report['cash_difference'] ?? 0, 2) }} NOK</div>
        </div>
        @if(!empty($report['tips_enabled']) && $report['tips_enabled'] === true)
            <div class="z-report-section" style="background-color: rgb(239 246 255); border-color: rgb(191 219 254);">
                <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                    <strong>Totalt Drikkepenger</strong>
                </div>
                <div style="font-size: 1.25rem; font-weight: 700; color: rgb(17 24 39);">{{ number_format(($report['total_tips'] ?? 0) / 100, 2) }} NOK</div>
            </div>
        @endif
    </div>

    <!-- VAT Breakdown -->
    <div class="z-report-section" style="background-color: rgb(249 250 251); border-color: rgb(229 231 235);">
        <h4 class="z-report-title">MVA-oppdeling</h4>
        <div class="z-report-grid z-report-grid-3">
            <div>
                <div class="z-report-metric-label" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">MVA-grunnlag</div>
                <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ number_format(($report['vat_base'] ?? 0) / 100, 2) }} NOK</div>
            </div>
            <div>
                <div class="z-report-metric-label" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">MVA-beløp ({{ $report['vat_rate'] ?? 25 }}%)</div>
                <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ number_format(($report['vat_amount'] ?? 0) / 100, 2) }} NOK</div>
            </div>
            <div>
                <div class="z-report-metric-label" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Totalt (inkl. MVA)</div>
                <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ number_format($report['total_amount'] / 100, 2) }} NOK</div>
            </div>
        </div>
    </div>

    <!-- Manual Discounts -->
    @if(isset($report['manual_discounts']) && $report['manual_discounts']['count'] > 0)
        <div class="z-report-section" style="background-color: rgb(249 250 251); border-color: rgb(229 231 235);">
            <h4 class="z-report-title">Manuelle Rabatter</h4>
            <div class="z-report-grid z-report-grid-2">
                <div>
                    <div class="z-report-metric-label">Antall Rabatter</div>
                    <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ $report['manual_discounts']['count'] }}</div>
                </div>
                <div>
                    <div class="z-report-metric-label">Totalt Rabattbeløp</div>
                    <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ number_format($report['manual_discounts']['amount'] / 100, 2) }} NOK</div>
                </div>
            </div>
        </div>
    @endif

    <!-- Line Corrections -->
    @if(isset($report['line_corrections']) && $report['line_corrections']['total_count'] > 0)
        <div class="z-report-section" style="background-color: rgb(249 250 251); border-color: rgb(229 231 235);">
            <h4 class="z-report-title">Linjekorreksjoner</h4>
            <div class="z-report-grid z-report-grid-2">
                <div>
                    <div class="z-report-metric-label">Antall Korreksjoner</div>
                    <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ $report['line_corrections']['total_count'] }}</div>
                </div>
                <div>
                    <div class="z-report-metric-label">Totalt Reduksjon</div>
                    <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ number_format($report['line_corrections']['total_amount_reduction'] / 100, 2) }} NOK</div>
                </div>
            </div>
            @if(isset($report['line_corrections']['by_type']) && count($report['line_corrections']['by_type']) > 0)
                <div style="margin-top: 1rem;">
                    <div style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; color: rgb(75 85 99);">Oppdeling etter Type:</div>
                    <div style="overflow-x: auto;">
                        <table class="z-report-table">
                            <thead>
                                <tr>
                                    <th style="text-align: left;">Type</th>
                                    <th style="text-align: center;">Antall</th>
                                    <th style="text-align: right;">Reduksjon</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['line_corrections']['by_type'] as $correction)
                                    <tr>
                                        <td style="text-transform: capitalize;">{{ $correction['type'] }}</td>
                                        <td style="text-align: center;">{{ $correction['count'] }}</td>
                                        <td style="text-align: right;">{{ number_format($correction['total_amount_reduction'] / 100, 2) }} NOK</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Activity Metrics -->
    <div class="z-report-grid z-report-grid-3">
        <div class="z-report-section" style="background-color: rgb(250 245 255); border-color: rgb(233 213 255);">
            <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                <strong>Kontantskuff-åpninger</strong>
            </div>
            <div style="font-size: 1.5rem; font-weight: 700; color: rgb(17 24 39);">{{ $report['cash_drawer_opens'] ?? 0 }}</div>
        </div>
        <div class="z-report-section" style="background-color: rgb(255 247 237); border-color: rgb(254 215 170);">
            <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                <strong>Nullinnslag Antall</strong>
            </div>
            <div style="font-size: 1.5rem; font-weight: 700; color: rgb(17 24 39);">{{ $report['nullinnslag_count'] ?? 0 }}</div>
        </div>
        <div class="z-report-section" style="background-color: rgb(240 253 244); border-color: rgb(187 247 208);">
            <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                <strong>Kvitteringer Generert</strong>
            </div>
            <div style="font-size: 1.5rem; font-weight: 700; color: rgb(17 24 39);">{{ $report['receipt_count'] ?? 0 }}</div>
            @if(isset($report['receipt_summary']) && count($report['receipt_summary']) > 0)
                <div style="font-size: 0.75rem; color: rgb(107 114 128); margin-top: 0.5rem;">
                    @foreach($report['receipt_summary'] as $type => $data)
                        {{ ucfirst($type) }}: {{ $data['count'] }}@if(!$loop->last), @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if(!empty($report['closing_notes']))
        <div class="z-report-section" style="background-color: rgb(249 250 251); border-color: rgb(229 231 235);">
            <div style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; color: rgb(17 24 39);">Stengningsnotater:</div>
            <div style="font-size: 0.875rem; color: rgb(75 85 99);">{{ $report['closing_notes'] }}</div>
        </div>
    @endif

    <!-- Sales by Vendor -->
    @if(isset($report['sales_by_vendor']) && count($report['sales_by_vendor']) > 0)
        <div class="z-report-section z-report-card">
            <h4 class="z-report-title">Salg per Leverandør</h4>
            <div style="overflow-x: auto;">
                <table class="z-report-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Leverandør</th>
                            <th style="text-align: center;">Antall</th>
                            <th style="text-align: right;">Beløp</th>
                            <th style="text-align: right;">Provision</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['sales_by_vendor'] as $vendor)
                            <tr>
                                <td style="font-weight: 500; color: rgb(17 24 39);">{{ $vendor['name'] }}</td>
                                <td style="text-align: center; color: rgb(17 24 39);">{{ $vendor['count'] }}</td>
                                <td style="text-align: right; font-weight: 600; color: rgb(17 24 39);">{{ number_format($vendor['amount'] / 100, 2) }} NOK</td>
                                <td style="text-align: right; color: rgb(17 24 39);">
                                    @if(isset($vendor['commission_percent']) && $vendor['commission_percent'] > 0)
                                        {{ number_format($vendor['commission_amount'] / 100, 2) }} NOK
                                        <span style="font-size: 0.75rem; color: rgb(107 114 128);">({{ number_format($vendor['commission_percent'], 2) }}%)</span>
                                    @else
                                        <span style="color: rgb(156 163 175);">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Event Summary -->
    @if(isset($report['event_summary']) && count($report['event_summary']) > 0)
        <div class="z-report-section z-report-card">
            <h4 class="z-report-title">Hendelsessammendrag</h4>
            <div style="overflow-x: auto;">
                <table class="z-report-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Hendelseskode</th>
                            <th style="text-align: left;">Beskrivelse</th>
                            <th style="text-align: right;">Antall</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['event_summary'] as $event)
                            <tr>
                                <td style="font-weight: 500; color: rgb(17 24 39);">{{ $event['code'] }}</td>
                                <td style="color: rgb(75 85 99);">{{ $event['description'] }}</td>
                                <td style="text-align: right; font-weight: 600; color: rgb(17 24 39);">{{ $event['count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Complete Transaction List -->
    @if(isset($report['complete_transaction_list']) && count($report['complete_transaction_list']) > 0)
        <div class="z-report-section z-report-card">
            <h4 class="z-report-title">Komplett Transaksjonsliste ({{ count($report['complete_transaction_list']) }} transaksjoner)</h4>
            <div class="z-report-scrollable">
                <table class="z-report-table">
                    <thead class="z-report-sticky-header">
                        <tr>
                            <th style="text-align: left;">{{ !empty($report['spans_multiple_days']) && $report['spans_multiple_days'] ? 'Dato & Tid' : 'Tid' }}</th>
                            <th style="text-align: left;">ID</th>
                            <th style="text-align: left;">Metode</th>
                            <th style="text-align: left;">Betalingskode</th>
                            <th style="text-align: left;">Transaksjonskode</th>
                            <th style="text-align: right;">Beløp</th>
                            @if(!empty($report['tips_enabled']) && $report['tips_enabled'] === true)
                                <th style="text-align: right;">Drikkepenger</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['complete_transaction_list'] as $transaction)
                            <tr>
                                <td style="color: rgb(75 85 99);">
                                    @if(!empty($transaction['spans_multiple_days']) && $transaction['spans_multiple_days'])
                                        {{ \Carbon\Carbon::parse($transaction['paid_at'] ?? $transaction['created_at'])->format('d.m.Y H:i:s') }}
                                    @else
                                        {{ \Carbon\Carbon::parse($transaction['paid_at'] ?? $transaction['created_at'])->format('H:i:s') }}
                                    @endif
                                </td>
                                <td style="font-size: 0.75rem; color: rgb(107 114 128);">{{ substr($transaction['stripe_charge_id'] ?? $transaction['id'], 0, 12) }}...</td>
                                <td>
                                    <span style="text-transform: capitalize; color: rgb(17 24 39);">{{ $transaction['payment_method'] ?? 'N/A' }}</span>
                                </td>
                                <td style="color: rgb(75 85 99);">{{ $transaction['payment_code'] ?? 'N/A' }}</td>
                                <td style="color: rgb(75 85 99);">{{ $transaction['transaction_code'] ?? 'N/A' }}</td>
                                <td style="text-align: right; font-weight: 600; color: rgb(17 24 39);">{{ number_format($transaction['amount'] / 100, 2) }} NOK</td>
                                @if(!empty($report['tips_enabled']) && $report['tips_enabled'] === true)
                                    <td style="text-align: right; color: rgb(75 85 99);">{{ ($transaction['tip_amount'] ?? 0) > 0 ? number_format($transaction['tip_amount'] / 100, 2) . ' NOK' : '-' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif(isset($report['charges']) && count($report['charges']) > 0)
        <div class="z-report-section z-report-card">
            <h4 class="z-report-title">Alle Transaksjoner</h4>
            <div style="overflow-x: auto;">
                <table class="z-report-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">{{ !empty($report['spans_multiple_days']) && $report['spans_multiple_days'] ? 'Dato & Tid' : 'Tid' }}</th>
                            <th style="text-align: left;">Metode</th>
                            <th style="text-align: left;">Kode</th>
                            <th style="text-align: right;">Beløp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['charges'] as $charge)
                            <tr>
                                <td style="color: rgb(75 85 99);">
                                    @if(!empty($report['spans_multiple_days']) && $report['spans_multiple_days'])
                                        {{ $charge->paid_at?->format('d.m.Y H:i') ?? $charge->created_at->format('d.m.Y H:i') }}
                                    @else
                                        {{ $charge->paid_at?->format('H:i') ?? $charge->created_at->format('H:i') }}
                                    @endif
                                </td>
                                <td>
                                    <span style="text-transform: capitalize; color: rgb(17 24 39);">{{ $charge->payment_method }}</span>
                                </td>
                                <td style="color: rgb(75 85 99);">{{ $charge->payment_code ?? 'N/A' }}</td>
                                <td style="text-align: right; font-weight: 600; color: rgb(17 24 39);">{{ number_format($charge->amount / 100, 2) }} NOK</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
