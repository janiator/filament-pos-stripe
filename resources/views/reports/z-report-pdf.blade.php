<!DOCTYPE html>
<html lang="nb">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Z-Rapport - {{ $session->session_number }}</title>
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
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
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
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .cash-grid.with-tips {
            grid-template-columns: repeat(5, 1fr);
        }
        .cash-item {
            padding: 12px;
            border-radius: 5px;
        }
        .cash-item.yellow {
            background-color: #fefce8;
            border: 1px solid #fde047;
        }
        .cash-item.purple {
            background-color: #faf5ff;
            border: 1px solid #e9d5ff;
        }
        .cash-item.red {
            background-color: #fef2f2;
            border: 1px solid #fca5a5;
        }
        .cash-item.green {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
        }
        .cash-item.blue {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
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
            font-size: 8pt;
        }
        table th {
            background-color: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 6px;
            text-align: left;
            font-weight: 600;
            color: #374151;
        }
        table td {
            padding: 6px;
            border-bottom: 1px solid #f3f4f6;
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
        <h1>Z-Rapport (Sluttrapport)</h1>
        <div class="header-info">
            <div><strong>Øktsnummer:</strong> {{ $session->session_number }}</div>
            <div><strong>Butikk:</strong> {{ $report['store']['name'] ?? 'N/A' }}</div>
            <div><strong>Åpnet:</strong> {{ $session->opened_at->format('d.m.Y H:i') }}</div>
            <div><strong>Stengt:</strong> {{ $session->closed_at?->format('d.m.Y H:i') ?? 'N/A' }}</div>
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

    <div class="cash-grid {{ (!empty($report['tips_enabled']) && $report['tips_enabled'] === true) ? 'with-tips' : '' }}">
        <div class="cash-item yellow">
            <strong>Åpningssaldo</strong>
            <div class="value">{{ number_format(($report['opening_balance'] ?? 0) / 100, 2) }} NOK</div>
        </div>
        <div class="cash-item yellow">
            <strong>Forventet Kontant</strong>
            <div class="value">{{ number_format($report['expected_cash'] / 100, 2) }} NOK</div>
        </div>
        <div class="cash-item purple">
            <strong>Faktisk Kontant</strong>
            <div class="value">{{ number_format(($report['actual_cash'] ?? 0) / 100, 2) }} NOK</div>
        </div>
        <div class="cash-item {{ ($report['cash_difference'] ?? 0) > 0 ? 'red' : (($report['cash_difference'] ?? 0) < 0 ? 'yellow' : 'green') }}">
            <strong>Differanse</strong>
            <div class="value">{{ number_format(($report['cash_difference'] ?? 0) / 100, 2) }} NOK</div>
        </div>
        @if(!empty($report['tips_enabled']) && $report['tips_enabled'] === true)
            <div class="cash-item blue">
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

    @if(!empty($report['closing_notes']))
        <div class="section">
            <div class="section-title">Stengningsnotater</div>
            <p style="font-size: 9pt; color: #4b5563;">{{ $report['closing_notes'] }}</p>
        </div>
    @endif

    @if(isset($report['sales_by_category']) && $report['sales_by_category']->count() > 0)
        <div class="section">
            <div class="section-title">Salg per Produktkategori</div>
            <table>
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th class="text-center">Antall</th>
                        <th class="text-right">Beløp</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['sales_by_category'] as $category)
                        <tr>
                            <td>{{ $category['name'] }}</td>
                            <td class="text-center">{{ $category['count'] }}</td>
                            <td class="text-right">{{ number_format($category['amount'] / 100, 2) }} NOK</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(isset($report['event_summary']) && $report['event_summary']->count() > 0)
        <div class="section">
            <div class="section-title">Hendelsessammendrag</div>
            <table>
                <thead>
                    <tr>
                        <th>Hendelseskode</th>
                        <th>Beskrivelse</th>
                        <th class="text-right">Antall</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['event_summary'] as $event)
                        <tr>
                            <td>{{ $event['code'] }}</td>
                            <td>{{ $event['description'] }}</td>
                            <td class="text-right">{{ $event['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(isset($report['complete_transaction_list']) && count($report['complete_transaction_list']) > 0)
        <div class="section page-break">
            <div class="section-title">Komplett Transaksjonsliste ({{ count($report['complete_transaction_list']) }} transaksjoner)</div>
            <table>
                <thead>
                    <tr>
                        <th>{{ !empty($report['spans_multiple_days']) && $report['spans_multiple_days'] ? 'Dato & Tid' : 'Tid' }}</th>
                        <th>ID</th>
                        <th>Metode</th>
                        <th>Betalingskode</th>
                        <th>Transaksjonskode</th>
                        <th class="text-right">Beløp</th>
                        @if(!empty($report['tips_enabled']) && $report['tips_enabled'] === true)
                            <th class="text-right">Drikkepenger</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['complete_transaction_list'] as $transaction)
                        <tr>
                            <td>
                                @if(!empty($transaction['spans_multiple_days']) && $transaction['spans_multiple_days'])
                                    {{ \Carbon\Carbon::parse($transaction['paid_at'] ?? $transaction['created_at'])->format('d.m.Y H:i:s') }}
                                @else
                                    {{ \Carbon\Carbon::parse($transaction['paid_at'] ?? $transaction['created_at'])->format('H:i:s') }}
                                @endif
                            </td>
                            <td style="font-size: 7pt;">{{ substr($transaction['stripe_charge_id'] ?? $transaction['id'], 0, 12) }}...</td>
                            <td>{{ ucfirst($transaction['payment_method'] ?? 'N/A') }}</td>
                            <td>{{ $transaction['payment_code'] ?? 'N/A' }}</td>
                            <td>{{ $transaction['transaction_code'] ?? 'N/A' }}</td>
                            <td class="text-right">{{ number_format($transaction['amount'] / 100, 2) }} NOK</td>
                            @if(!empty($report['tips_enabled']) && $report['tips_enabled'] === true)
                                <td class="text-right">{{ ($transaction['tip_amount'] ?? 0) > 0 ? number_format($transaction['tip_amount'] / 100, 2) . ' NOK' : '-' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
