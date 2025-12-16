<!DOCTYPE html>
<html lang="nb">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>X-Rapport - {{ $session->session_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.4;
        }
        .header {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18pt;
            font-weight: 600;
            margin-bottom: 15px;
            color: #111827;
        }
        .header-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            font-size: 9pt;
        }
        .header-info div {
            margin-bottom: 5px;
        }
        .header-info strong {
            color: #6b7280;
        }
        .metrics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .metric-card {
            background-color: #fff;
            border: 1px solid #e5e7eb;
            padding: 15px;
            border-radius: 5px;
        }
        .metric-label {
            font-size: 8pt;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .metric-value {
            font-size: 16pt;
            font-weight: 700;
            color: #111827;
        }
        .section {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .section-title {
            font-size: 12pt;
            font-weight: 600;
            margin-bottom: 12px;
            color: #111827;
        }
        .cash-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .cash-item {
            background-color: #fefce8;
            border: 1px solid #fde047;
            padding: 12px;
            border-radius: 5px;
        }
        .cash-item strong {
            font-size: 9pt;
            color: #4b5563;
            display: block;
            margin-bottom: 5px;
        }
        .cash-item .value {
            font-size: 14pt;
            font-weight: 700;
            color: #111827;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table th {
            background-color: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 8px;
            text-align: left;
            font-weight: 600;
            font-size: 9pt;
            color: #374151;
        }
        table td {
            padding: 8px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 9pt;
            color: #4b5563;
        }
        table tr:last-child td {
            border-bottom: none;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .page-break {
            page-break-after: always;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>X-Rapport (Mellomrapport)</h1>
        <div class="header-info">
            <div><strong>Øktsnummer:</strong> {{ $session->session_number }}</div>
            <div><strong>Butikk:</strong> {{ $report['store']['name'] ?? 'N/A' }}</div>
            <div><strong>Åpnet:</strong> {{ $session->opened_at->format('d.m.Y H:i') }}</div>
            <div><strong>Generert:</strong> {{ is_string($report['report_generated_at']) ? \Carbon\Carbon::parse($report['report_generated_at'])->format('d.m.Y H:i') : $report['report_generated_at']->format('d.m.Y H:i') }}</div>
            @if($report['device'])
                <div><strong>Enhet:</strong> {{ $report['device']['name'] }}</div>
            @endif
            @if($report['cashier'])
                <div><strong>Kasserer:</strong> {{ $report['cashier']['name'] }}</div>
            @endif
        </div>
    </div>

    <div class="metrics">
        <div class="metric-card">
            <div class="metric-label">Transaksjoner</div>
            <div class="metric-value">{{ $report['transactions_count'] }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Totalt Beløp</div>
            <div class="metric-value">{{ number_format($report['total_amount'] / 100, 2) }} NOK</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Kontant</div>
            <div class="metric-value">{{ number_format($report['cash_amount'] / 100, 2) }} NOK</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Kort</div>
            <div class="metric-value">{{ number_format($report['card_amount'] / 100, 2) }} NOK</div>
        </div>
    </div>

    <div class="cash-grid">
        <div class="cash-item">
            <strong>Åpningssaldo</strong>
            <div class="value">{{ number_format($report['opening_balance'] ?? 0, 2) }} NOK</div>
        </div>
        <div class="cash-item">
            <strong>Forventet Kontant</strong>
            <div class="value">{{ number_format($report['expected_cash'], 2) }} NOK</div>
        </div>
        @if(!empty($report['tips_enabled']) && $report['tips_enabled'] === true)
            <div class="cash-item" style="background-color: #eff6ff; border-color: #bfdbfe;">
                <strong>Totalt Drikkepenger</strong>
                <div class="value">{{ number_format(($report['total_tips'] ?? 0) / 100, 2) }} NOK</div>
            </div>
        @endif
    </div>

    <div class="section">
        <div class="section-title">MVA-oppdeling</div>
        <table>
            <tr>
                <th>MVA-grunnlag</th>
                <th>MVA-beløp ({{ $report['vat_rate'] ?? 25 }}%)</th>
                <th>Totalt (inkl. MVA)</th>
            </tr>
            <tr>
                <td>{{ number_format(($report['vat_base'] ?? 0) / 100, 2) }} NOK</td>
                <td>{{ number_format(($report['vat_amount'] ?? 0) / 100, 2) }} NOK</td>
                <td>{{ number_format($report['total_amount'] / 100, 2) }} NOK</td>
            </tr>
        </table>
    </div>

    @if(isset($report['manual_discounts']) && $report['manual_discounts']['count'] > 0)
        <div class="section">
            <div class="section-title">Manuelle Rabatter</div>
            <table>
                <tr>
                    <th>Antall Rabatter</th>
                    <th class="text-right">Totalt Rabattbeløp</th>
                </tr>
                <tr>
                    <td>{{ $report['manual_discounts']['count'] }}</td>
                    <td class="text-right">{{ number_format($report['manual_discounts']['amount'] / 100, 2) }} NOK</td>
                </tr>
            </table>
        </div>
    @endif

    @if(isset($report['line_corrections']) && $report['line_corrections']['total_count'] > 0)
        <div class="section">
            <div class="section-title">Linjekorreksjoner</div>
            <table>
                <tr>
                    <th>Type</th>
                    <th class="text-center">Antall</th>
                    <th class="text-right">Reduksjon</th>
                </tr>
                @if(isset($report['line_corrections']['by_type']) && $report['line_corrections']['by_type']->count() > 0)
                    @foreach($report['line_corrections']['by_type'] as $correction)
                        <tr>
                            <td>{{ ucfirst($correction['type']) }}</td>
                            <td class="text-center">{{ $correction['count'] }}</td>
                            <td class="text-right">{{ number_format($correction['total_amount_reduction'] / 100, 2) }} NOK</td>
                        </tr>
                    @endforeach
                @endif
                <tr style="font-weight: 600; border-top: 2px solid #e5e7eb;">
                    <td>Totalt</td>
                    <td class="text-center">{{ $report['line_corrections']['total_count'] }}</td>
                    <td class="text-right">{{ number_format($report['line_corrections']['total_amount_reduction'] / 100, 2) }} NOK</td>
                </tr>
            </table>
        </div>
    @endif

    <div class="metrics">
        <div class="metric-card">
            <div class="metric-label">Kontantskuff-åpninger</div>
            <div class="metric-value">{{ $report['cash_drawer_opens'] ?? 0 }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Nullinnslag Antall</div>
            <div class="metric-value">{{ $report['nullinnslag_count'] ?? 0 }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Kvitteringer Generert</div>
            <div class="metric-value">{{ $report['receipt_count'] ?? 0 }}</div>
        </div>
    </div>

    @if(isset($report['by_payment_code']) && $report['by_payment_code']->count() > 0)
        <div class="section">
            <div class="section-title">Oppdeling etter Betalingskode</div>
            <table>
                <thead>
                    <tr>
                        <th>Betalingskode</th>
                        <th class="text-center">Antall</th>
                        <th class="text-right">Beløp</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['by_payment_code'] as $code => $data)
                        <tr>
                            <td>{{ $code }}</td>
                            <td class="text-center">{{ $data['count'] }}</td>
                            <td class="text-right">{{ number_format($data['amount'] / 100, 2) }} NOK</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(isset($report['sales_by_vendor']) && $report['sales_by_vendor']->count() > 0)
        <div class="section">
            <div class="section-title">Salg per Leverandør</div>
            <table>
                <thead>
                    <tr>
                        <th>Leverandør</th>
                        <th class="text-center">Antall</th>
                        <th class="text-right">Beløp</th>
                        <th class="text-right">Provision</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['sales_by_vendor'] as $vendor)
                        <tr>
                            <td>{{ $vendor['name'] }}</td>
                            <td class="text-center">{{ $vendor['count'] }}</td>
                            <td class="text-right">{{ number_format($vendor['amount'] / 100, 2) }} NOK</td>
                            <td class="text-right">
                                @if(isset($vendor['commission_percent']) && $vendor['commission_percent'] > 0)
                                    {{ number_format($vendor['commission_amount'] / 100, 2) }} NOK
                                    <span style="font-size: 8pt; color: #6b7280;">({{ number_format($vendor['commission_percent'], 2) }}%)</span>
                                @else
                                    <span style="color: #9ca3af;">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($report['charges']->count() > 0)
        <div class="section">
            <div class="section-title">Siste Transaksjoner</div>
            <table>
                <thead>
                    <tr>
                        <th>Tid</th>
                        <th>Metode</th>
                        <th class="text-right">Beløp</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['charges']->take(10) as $charge)
                        <tr>
                            <td>{{ $charge->paid_at?->format('H:i') ?? $charge->created_at->format('H:i') }}</td>
                            <td>{{ ucfirst($charge->payment_method) }}</td>
                            <td class="text-right">{{ number_format($charge->amount / 100, 2) }} NOK</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>

