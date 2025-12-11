<style>
    .x-report-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    .x-report-section {
        padding: 1.25rem;
        border-radius: 0.5rem;
        border-width: 1px;
    }
    .x-report-header {
        background-color: rgb(239 246 255);
        border-color: rgb(191 219 254);
    }
    .dark .x-report-header {
        background-color: rgba(30, 58, 138, 0.2);
        border-color: rgb(30 58 138);
    }
    .x-report-card {
        background-color: white;
        border-color: rgb(229 231 235);
    }
    .dark .x-report-card {
        background-color: rgb(31 41 55);
        border-color: rgb(55 65 81);
    }
    .x-report-metric-label {
        font-size: 0.875rem;
        color: rgb(107 114 128);
        margin-bottom: 0.5rem;
    }
    .dark .x-report-metric-label {
        color: rgb(156 163 175);
    }
    .x-report-metric-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }
    .dark .x-report-metric-value {
        color: white;
    }
    .x-report-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: rgb(17 24 39);
    }
    .dark .x-report-title {
        color: white;
    }
    .x-report-grid {
        display: grid;
        gap: 1rem;
    }
    .x-report-grid-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .x-report-grid-3 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
    .x-report-grid-4 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    @media (min-width: 768px) {
        .x-report-grid-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .x-report-grid-4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }
    .x-report-table {
        width: 100%;
        font-size: 0.875rem;
    }
    .x-report-table thead {
        background-color: rgb(249 250 251);
        border-bottom-width: 1px;
        border-color: rgb(229 231 235);
    }
    .dark .x-report-table thead {
        background-color: rgb(17 24 39);
        border-color: rgb(55 65 81);
    }
    .x-report-table th {
        padding: 0.75rem;
        text-align: left;
        font-weight: 600;
        color: rgb(55 65 81);
    }
    .dark .x-report-table th {
        color: rgb(209 213 219);
    }
    .x-report-table td {
        padding: 0.75rem;
        border-bottom-width: 1px;
        border-color: rgb(243 244 246);
        color: rgb(75 85 99);
    }
    .dark .x-report-table td {
        border-color: rgb(31 41 55);
        color: rgb(156 163 175);
    }
    .x-report-table tbody tr:hover {
        background-color: rgb(249 250 251);
    }
    .dark .x-report-table tbody tr:hover {
        background-color: rgb(17 24 39);
    }
</style>

<div class="x-report-container">
    <!-- Download Button -->
    <div style="margin-bottom: 1rem; text-align: right;">
        <a href="{{ route('reports.x-report.pdf', ['tenant' => $session->store->slug, 'sessionId' => $session->id]) }}" 
           target="_blank"
           style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background-color: rgb(239 68 68); color: white; text-decoration: none; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500;">
            <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Last ned PDF
        </a>
    </div>
    
    <!-- Header Section -->
    <div class="x-report-section x-report-header">
        <h3 class="x-report-title">X-Rapport (Mellomrapport)</h3>
        <div class="x-report-grid x-report-grid-4" style="font-size: 0.875rem;">
            <div>
                <div class="x-report-metric-label">Øktsnummer</div>
                <div style="font-weight: 600; color: rgb(17 24 39);">{{ $session->session_number }}</div>
            </div>
            <div>
                <div class="x-report-metric-label">Butikk</div>
                <div style="font-weight: 600; color: rgb(17 24 39);">{{ $report['store']['name'] ?? 'N/A' }}</div>
            </div>
            <div>
                <div class="x-report-metric-label">Åpnet</div>
                <div style="font-weight: 600; color: rgb(17 24 39);">{{ $session->opened_at->format('d.m.Y H:i') }}</div>
            </div>
            <div>
                <div class="x-report-metric-label">Generert</div>
                <div style="font-weight: 600; color: rgb(17 24 39);">{{ is_string($report['report_generated_at']) ? \Carbon\Carbon::parse($report['report_generated_at'])->format('d.m.Y H:i') : $report['report_generated_at']->format('d.m.Y H:i') }}</div>
            </div>
            @if($report['device'])
                <div>
                    <div class="x-report-metric-label">Enhet</div>
                    <div style="font-weight: 600; color: rgb(17 24 39);">{{ $report['device']['name'] }}</div>
                </div>
            @endif
            @if($report['cashier'])
                <div>
                    <div class="x-report-metric-label">Kasserer</div>
                    <div style="font-weight: 600; color: rgb(17 24 39);">{{ $report['cashier']['name'] }}</div>
                </div>
            @endif
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="x-report-grid x-report-grid-4">
        <div class="x-report-section x-report-card">
            <div class="x-report-metric-label">Transaksjoner</div>
            <div class="x-report-metric-value">{{ $report['transactions_count'] }}</div>
        </div>
        <div class="x-report-section x-report-card">
            <div class="x-report-metric-label">Totalt Beløp</div>
            <div class="x-report-metric-value">{{ number_format($report['total_amount'] / 100, 2) }} NOK</div>
        </div>
        <div class="x-report-section x-report-card">
            <div class="x-report-metric-label">Kontant</div>
            <div class="x-report-metric-value">{{ number_format($report['cash_amount'] / 100, 2) }} NOK</div>
        </div>
        <div class="x-report-section x-report-card">
            <div class="x-report-metric-label">Kort</div>
            <div class="x-report-metric-value">{{ number_format($report['card_amount'] / 100, 2) }} NOK</div>
        </div>
    </div>

    @if($report['mobile_amount'] > 0 || $report['other_amount'] > 0)
        <div class="x-report-grid x-report-grid-2">
            @if($report['mobile_amount'] > 0)
                <div class="x-report-section x-report-card">
                    <div class="x-report-metric-label">Mobil</div>
                    <div class="x-report-metric-value" style="font-size: 1.25rem;">{{ number_format($report['mobile_amount'] / 100, 2) }} NOK</div>
                </div>
            @endif
            @if($report['other_amount'] > 0)
                <div class="x-report-section x-report-card">
                    <div class="x-report-metric-label">Annet</div>
                    <div class="x-report-metric-value" style="font-size: 1.25rem;">{{ number_format($report['other_amount'] / 100, 2) }} NOK</div>
                </div>
            @endif
        </div>
    @endif

    <!-- Cash Management -->
    <div class="x-report-grid x-report-grid-3">
        <div class="x-report-section" style="background-color: rgb(254 252 232); border-color: rgb(253 224 71);">
            <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                <strong>Åpningssaldo</strong>
            </div>
            <div style="font-size: 1.25rem; font-weight: 700; color: rgb(17 24 39);">{{ number_format(($report['opening_balance'] ?? 0) / 100, 2) }} NOK</div>
        </div>
        <div class="x-report-section" style="background-color: rgb(254 252 232); border-color: rgb(253 224 71);">
            <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                <strong>Forventet Kontant</strong>
            </div>
            <div style="font-size: 1.25rem; font-weight: 700; color: rgb(17 24 39);">{{ number_format($report['expected_cash'] / 100, 2) }} NOK</div>
        </div>
        @if(!empty($report['tips_enabled']) && $report['tips_enabled'] === true)
            <div class="x-report-section" style="background-color: rgb(239 246 255); border-color: rgb(191 219 254);">
                <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                    <strong>Totalt Drikkepenger</strong>
                </div>
                <div style="font-size: 1.25rem; font-weight: 700; color: rgb(17 24 39);">{{ number_format(($report['total_tips'] ?? 0) / 100, 2) }} NOK</div>
            </div>
        @endif
    </div>

    <!-- VAT Breakdown -->
    <div class="x-report-section" style="background-color: rgb(249 250 251); border-color: rgb(229 231 235);">
        <h4 class="x-report-title">MVA-oppdeling</h4>
        <div class="x-report-grid x-report-grid-3">
            <div>
                <div class="x-report-metric-label" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">MVA-grunnlag</div>
                <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ number_format(($report['vat_base'] ?? 0) / 100, 2) }} NOK</div>
            </div>
            <div>
                <div class="x-report-metric-label" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">MVA-beløp ({{ $report['vat_rate'] ?? 25 }}%)</div>
                <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ number_format(($report['vat_amount'] ?? 0) / 100, 2) }} NOK</div>
            </div>
            <div>
                <div class="x-report-metric-label" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Totalt (inkl. MVA)</div>
                <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ number_format($report['total_amount'] / 100, 2) }} NOK</div>
            </div>
        </div>
    </div>

    <!-- Manual Discounts -->
    @if(isset($report['manual_discounts']) && $report['manual_discounts']['count'] > 0)
        <div class="x-report-section" style="background-color: rgb(249 250 251); border-color: rgb(229 231 235);">
            <h4 class="x-report-title">Manuelle Rabatter</h4>
            <div class="x-report-grid x-report-grid-2">
                <div>
                    <div class="x-report-metric-label">Antall Rabatter</div>
                    <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ $report['manual_discounts']['count'] }}</div>
                </div>
                <div>
                    <div class="x-report-metric-label">Totalt Rabattbeløp</div>
                    <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ number_format($report['manual_discounts']['amount'] / 100, 2) }} NOK</div>
                </div>
            </div>
        </div>
    @endif

    <!-- Line Corrections -->
    @if(isset($report['line_corrections']) && $report['line_corrections']['total_count'] > 0)
        <div class="x-report-section" style="background-color: rgb(249 250 251); border-color: rgb(229 231 235);">
            <h4 class="x-report-title">Linjekorreksjoner</h4>
            <div class="x-report-grid x-report-grid-2">
                <div>
                    <div class="x-report-metric-label">Antall Korreksjoner</div>
                    <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ $report['line_corrections']['total_count'] }}</div>
                </div>
                <div>
                    <div class="x-report-metric-label">Totalt Reduksjon</div>
                    <div style="font-size: 1.125rem; font-weight: 600; color: rgb(17 24 39);">{{ number_format($report['line_corrections']['total_amount_reduction'] / 100, 2) }} NOK</div>
                </div>
            </div>
            @if(isset($report['line_corrections']['by_type']) && $report['line_corrections']['by_type']->count() > 0)
                <div style="margin-top: 1rem;">
                    <div style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; color: rgb(75 85 99);">Oppdeling etter Type:</div>
                    <div style="overflow-x: auto;">
                        <table class="x-report-table">
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
    <div class="x-report-grid x-report-grid-3">
        <div class="x-report-section" style="background-color: rgb(250 245 255); border-color: rgb(233 213 255);">
            <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                <strong>Kontantskuff-åpninger</strong>
            </div>
            <div style="font-size: 1.5rem; font-weight: 700; color: rgb(17 24 39);">{{ $report['cash_drawer_opens'] ?? 0 }}</div>
        </div>
        <div class="x-report-section" style="background-color: rgb(255 247 237); border-color: rgb(254 215 170);">
            <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                <strong>Nullinnslag Antall</strong>
            </div>
            <div style="font-size: 1.5rem; font-weight: 700; color: rgb(17 24 39);">{{ $report['nullinnslag_count'] ?? 0 }}</div>
        </div>
        <div class="x-report-section" style="background-color: rgb(240 253 244); border-color: rgb(187 247 208);">
            <div style="font-size: 0.875rem; color: rgb(75 85 99); margin-bottom: 0.5rem;">
                <strong>Kvitteringer Generert</strong>
            </div>
            <div style="font-size: 1.5rem; font-weight: 700; color: rgb(17 24 39);">{{ $report['receipt_count'] ?? 0 }}</div>
        </div>
    </div>

    <!-- Payment Code Breakdown -->
    @if(isset($report['by_payment_code']) && $report['by_payment_code']->count() > 0)
        <div class="x-report-section x-report-card">
            <h4 class="x-report-title">Oppdeling etter Betalingskode</h4>
            <div style="overflow-x: auto;">
                <table class="x-report-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Betalingskode</th>
                            <th style="text-align: center;">Antall</th>
                            <th style="text-align: right;">Beløp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['by_payment_code'] as $code => $data)
                            <tr>
                                <td style="font-weight: 500; color: rgb(17 24 39);">{{ $code }}</td>
                                <td style="text-align: center; color: rgb(17 24 39);">{{ $data['count'] }}</td>
                                <td style="text-align: right; font-weight: 600; color: rgb(17 24 39);">{{ number_format($data['amount'] / 100, 2) }} NOK</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Sales by Category -->
    @if(isset($report['sales_by_category']) && $report['sales_by_category']->count() > 0)
        <div class="x-report-section x-report-card">
            <h4 class="x-report-title">Salg per Produktkategori</h4>
            <div style="overflow-x: auto;">
                <table class="x-report-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Kategori</th>
                            <th style="text-align: center;">Antall</th>
                            <th style="text-align: right;">Beløp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['sales_by_category'] as $category)
                            <tr>
                                <td style="font-weight: 500; color: rgb(17 24 39);">{{ $category['name'] }}</td>
                                <td style="text-align: center; color: rgb(17 24 39);">{{ $category['count'] }}</td>
                                <td style="text-align: right; font-weight: 600; color: rgb(17 24 39);">{{ number_format($category['amount'] / 100, 2) }} NOK</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Recent Transactions -->
    @if($report['charges']->count() > 0)
        <div class="x-report-section" style="background-color: rgb(249 250 251); border-color: rgb(229 231 235);">
            <h4 class="x-report-title">Siste Transaksjoner</h4>
            <div style="overflow-x: auto;">
                <table class="x-report-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Tid</th>
                            <th style="text-align: left;">Metode</th>
                            <th style="text-align: right;">Beløp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['charges']->take(10) as $charge)
                            <tr>
                                <td style="color: rgb(75 85 99);">{{ $charge->paid_at?->format('H:i') ?? $charge->created_at->format('H:i') }}</td>
                                <td>
                                    <span style="text-transform: capitalize; color: rgb(17 24 39);">{{ $charge->payment_method }}</span>
                                </td>
                                <td style="text-align: right; font-weight: 600; color: rgb(17 24 39);">{{ number_format($charge->amount / 100, 2) }} NOK</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
