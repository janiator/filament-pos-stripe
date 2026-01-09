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
            padding: 15px;
            background-color: #fff;
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
            display: table;
            width: 100%;
            font-size: 9pt;
        }
        .header-info > div {
            display: table-cell;
            width: 50%;
            padding-right: 15px;
            vertical-align: top;
            margin-bottom: 5px;
        }
        .header-info strong {
            color: #6b7280;
        }
        .metrics {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .metrics > div {
            display: table-cell;
            width: 25%;
            padding: 0 7px;
            vertical-align: top;
        }
        .metrics > div:first-child {
            padding-left: 0;
        }
        .metrics > div:last-child {
            padding-right: 0;
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
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .cash-grid > div {
            display: table-cell;
            width: 25%;
            padding: 0 7px;
            vertical-align: top;
        }
        .cash-grid.with-tips > div {
            width: 20%;
        }
        .cash-grid > div:first-child {
            padding-left: 0;
        }
        .cash-grid > div:last-child {
            padding-right: 0;
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
            font-size: 7pt;
        }
        table th {
            background-color: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 5px 4px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 7pt;
        }
        table td {
            padding: 5px 4px;
            border-bottom: 1px solid #f3f4f6;
            color: #4b5563;
            font-size: 7pt;
            word-wrap: break-word;
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
        .transaction-table {
            font-size: 6.5pt;
        }
        .transaction-table th {
            font-size: 6.5pt;
            padding: 4px 3px;
        }
        .transaction-table td {
            font-size: 6.5pt;
            padding: 4px 3px;
        }
        .status-succeeded {
            color: #22c55e;
            font-weight: 600;
        }
        .status-refunded {
            color: #dc2626;
            font-weight: 600;
        }
        .status-pending {
            color: #eab308;
            font-weight: 600;
        }
        .status-processing {
            color: #3b82f6;
            font-weight: 600;
        }
        .status-failed, .status-cancelled {
            color: #6b7280;
            font-weight: 600;
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
        <div>
            <div class="metric-card">
                <div class="metric-label">Transaksjoner</div>
                <div class="metric-value">{{ $report['transactions_count'] }}</div>
            </div>
        </div>
        <div>
            <div class="metric-card">
                <div class="metric-label">Totalt Beløp</div>
                <div class="metric-value">{{ number_format($report['total_amount'] / 100, 2) }} NOK</div>
                @if(isset($report['total_refunded']) && $report['total_refunded'] > 0)
                    <div style="font-size: 7pt; color: #dc2626; margin-top: 4px;">
                        Refusjoner: -{{ number_format($report['total_refunded'] / 100, 2) }} NOK
                    </div>
                    <div style="font-size: 8pt; font-weight: 600; color: #111827; margin-top: 2px;">
                        Netto: {{ number_format(($report['net_amount'] ?? ($report['total_amount'] - $report['total_refunded'])) / 100, 2) }} NOK
                    </div>
                @endif
            </div>
        </div>
        <div>
            <div class="metric-card">
                <div class="metric-label">Kontant</div>
                <div class="metric-value">{{ number_format($report['cash_amount'] / 100, 2) }} NOK</div>
                @if(isset($report['cash_refunded']) && $report['cash_refunded'] > 0)
                    <div style="font-size: 7pt; color: #dc2626; margin-top: 4px;">
                        Refusjoner: -{{ number_format($report['cash_refunded'] / 100, 2) }} NOK
                    </div>
                    <div style="font-size: 8pt; font-weight: 600; color: #111827; margin-top: 2px;">
                        Netto: {{ number_format(($report['cash_amount'] - ($report['cash_refunded'] ?? 0)) / 100, 2) }} NOK
                    </div>
                @endif
            </div>
        </div>
        <div>
            <div class="metric-card">
                <div class="metric-label">Kort</div>
                <div class="metric-value">{{ number_format($report['card_amount'] / 100, 2) }} NOK</div>
            </div>
        </div>
    </div>

    <div class="cash-grid {{ (!empty($report['tips_enabled']) && $report['tips_enabled'] === true) ? 'with-tips' : '' }}">
        <div>
            <div class="cash-item yellow">
                <strong>Åpningssaldo</strong>
                <div class="value">{{ number_format($report['opening_balance'] ?? 0, 2) }} NOK</div>
            </div>
        </div>
        <div>
            <div class="cash-item yellow">
                <strong>Forventet Kontant</strong>
                <div class="value">{{ number_format($report['expected_cash'], 2) }} NOK</div>
            </div>
        </div>
        <div>
            <div class="cash-item purple">
                <strong>Faktisk Kontant</strong>
                <div class="value">{{ number_format($report['actual_cash'] ?? 0, 2) }} NOK</div>
            </div>
        </div>
        <div>
            <div class="cash-item {{ ($report['cash_difference'] ?? 0) > 0 ? 'red' : (($report['cash_difference'] ?? 0) < 0 ? 'yellow' : 'green') }}">
                <strong>Differanse</strong>
                <div class="value">{{ number_format($report['cash_difference'] ?? 0, 2) }} NOK</div>
            </div>
        </div>
        @if(!empty($report['tips_enabled']) && $report['tips_enabled'] === true)
            <div>
                <div class="cash-item blue">
                    <strong>Totalt Drikkepenger</strong>
                    <div class="value">{{ number_format(($report['total_tips'] ?? 0) / 100, 2) }} NOK</div>
                </div>
            </div>
        @endif
    </div>

    @if(isset($report['refunds']) && count($report['refunds']) > 0)
        <div class="section">
            <div class="section-title">Refusjoner ({{ count($report['refunds']) }} refusjoner)</div>
            <table>
                <thead>
                    <tr>
                        <th>Tid</th>
                        <th>ID</th>
                        <th>Metode</th>
                        <th>Beskrivelse</th>
                        <th class="text-right">Opprinnelig Beløp</th>
                        <th class="text-right">Refundert Beløp</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['refunds'] as $refund)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($refund['refunded_at'] ?? $refund['created_at'] ?? $refund['paid_at'])->format('H:i:s') }}</td>
                            <td style="font-size: 6pt;">{{ substr($refund['stripe_charge_id'] ?? $refund['charge_id'] ?? $refund['id'], 0, 10) }}...</td>
                            <td>{{ ucfirst($refund['payment_method'] ?? 'N/A') }}</td>
                            <td>{{ $refund['description'] ?? 'Cash payment' }}</td>
                            <td class="text-right">{{ number_format(($refund['amount'] ?? $refund['original_amount'] ?? 0) / 100, 2) }} NOK</td>
                            <td class="text-right" style="color: #dc2626;">-{{ number_format(($refund['amount_refunded'] ?? 0) / 100, 2) }} NOK</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top: 10px; padding-top: 10px; border-top: 2px solid #e5e7eb;">
                <div style="display: table; width: 100%;">
                    <div style="display: table-cell; width: 70%; font-weight: 600; color: #111827;">
                        Totalt Refundert:
                    </div>
                    <div style="display: table-cell; width: 30%; text-align: right; font-weight: 700; color: #dc2626; font-size: 11pt;">
                        {{ number_format($report['total_refunded'] / 100, 2) }} NOK
                    </div>
                </div>
                <div style="display: table; width: 100%; margin-top: 5px;">
                    <div style="display: table-cell; width: 70%; font-weight: 600; color: #111827;">
                        Netto Beløp:
                    </div>
                    <div style="display: table-cell; width: 30%; text-align: right; font-weight: 700; color: #111827; font-size: 11pt;">
                        {{ number_format(($report['net_amount'] ?? ($report['total_amount'] - $report['total_refunded'])) / 100, 2) }} NOK
                    </div>
                </div>
            </div>
        </div>
    @endif

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
                @if(isset($report['line_corrections']['by_type']) && count($report['line_corrections']['by_type']) > 0)
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
        <div>
            <div class="metric-card">
                <div class="metric-label">Kontantskuff-åpninger</div>
                <div class="metric-value">{{ $report['cash_drawer_opens'] ?? 0 }}</div>
            </div>
        </div>
        <div>
            <div class="metric-card">
                <div class="metric-label">Nullinnslag Antall</div>
                <div class="metric-value">{{ $report['nullinnslag_count'] ?? 0 }}</div>
            </div>
        </div>
        <div>
            <div class="metric-card">
                <div class="metric-label">Kvitteringer Generert</div>
                <div class="metric-value">{{ $report['receipt_count'] ?? 0 }}</div>
            </div>
        </div>
        <div></div>
    </div>

    @if(!empty($report['closing_notes']))
        <div class="section">
            <div class="section-title">Stengningsnotater</div>
            <p style="font-size: 9pt; color: #4b5563;">{{ $report['closing_notes'] }}</p>
        </div>
    @endif

    @if(isset($report['products_sold']) && count($report['products_sold']) > 0)
        <div class="section">
            <div class="section-title">Solgte Produkter ({{ count($report['products_sold']) }} produkter)</div>
            <table>
                <thead>
                    <tr>
                        <th>Produkt</th>
                        <th class="text-center">Antall</th>
                        <th class="text-right">Beløp</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['products_sold'] as $product)
                        <tr>
                            <td>{{ $product['name'] }}</td>
                            <td class="text-center">{{ $product['quantity'] }}</td>
                            <td class="text-right">{{ number_format($product['amount'] / 100, 2) }} NOK</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(isset($report['sales_by_vendor']) && count($report['sales_by_vendor']) > 0)
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
                                    <span style="font-size: 6pt; color: #6b7280;">({{ number_format($vendor['commission_percent'], 2) }}%)</span>
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

    @if(isset($report['event_summary']) && count($report['event_summary']) > 0)
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
            <table class="transaction-table">
                <thead>
                    <tr>
                        <th style="width: 12%;">{{ !empty($report['spans_multiple_days']) && $report['spans_multiple_days'] ? 'Dato & Tid' : 'Tid' }}</th>
                        <th style="width: 10%;">ID</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 10%;">Metode</th>
                        <th style="width: 8%;">Bet. Kode</th>
                        <th style="width: 8%;">Trans. Kode</th>
                        <th style="width: 15%; text-align: right;">Beløp</th>
                        @if(!empty($report['tips_enabled']) && $report['tips_enabled'] === true)
                            <th style="width: 12%; text-align: right;">Drikkepenger</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['complete_transaction_list'] as $transaction)
                        @php
                            $isRefunded = isset($transaction['amount_refunded']) && $transaction['amount_refunded'] > 0;
                            $refundedAmount = $transaction['amount_refunded'] ?? 0;
                            $status = $transaction['status'] ?? 'unknown';
                            $isPending = $status === 'pending';
                            $isDeferred = $transaction['is_deferred'] ?? false;
                            
                            $statusLabel = match($status) {
                                'succeeded' => 'Fullført',
                                'refunded' => 'Refundert',
                                'pending' => $isDeferred ? 'Venter (Utlevert)' : 'Venter',
                                'processing' => 'Behandler',
                                'failed' => 'Feilet',
                                'cancelled' => 'Kansellert',
                                default => ucfirst($status),
                            };
                            
                            $statusClass = match($status) {
                                'succeeded' => 'status-succeeded',
                                'refunded' => 'status-refunded',
                                'pending' => 'status-pending',
                                'processing' => 'status-processing',
                                'failed', 'cancelled' => 'status-failed',
                                default => '',
                            };
                        @endphp
                        <tr>
                            <td>
                                @if(!empty($transaction['spans_multiple_days']) && $transaction['spans_multiple_days'])
                                    {{ \Carbon\Carbon::parse($transaction['paid_at'] ?? $transaction['created_at'])->format('d.m.Y H:i:s') }}
                                @else
                                    {{ \Carbon\Carbon::parse($transaction['paid_at'] ?? $transaction['created_at'])->format('H:i:s') }}
                                @endif
                            </td>
                            <td style="font-size: 6pt;">{{ substr($transaction['stripe_charge_id'] ?? $transaction['id'], 0, 10) }}...</td>
                            <td>
                                <span class="{{ $statusClass }}">{{ $statusLabel }}</span>
                                @if($isPending)
                                    <div style="font-size: 5pt; color: #6b7280; margin-top: 1px;">
                                        (Ikke inkl.)
                                    </div>
                                @endif
                            </td>
                            <td>{{ ucfirst($transaction['payment_method'] ?? 'N/A') }}</td>
                            <td>{{ $transaction['payment_code'] ?? 'N/A' }}</td>
                            <td>{{ $transaction['transaction_code'] ?? 'N/A' }}</td>
                            <td class="text-right" style="font-weight: 600; color: {{ $isPending ? '#6b7280' : ($isRefunded ? '#dc2626' : '#111827') }}; {{ $isPending ? 'font-style: italic;' : '' }}">
                                {{ number_format($transaction['amount'] / 100, 2) }} NOK
                                @if($isRefunded)
                                    <div style="font-size: 5.5pt; color: #dc2626; margin-top: 1px;">
                                        Ref: -{{ number_format($refundedAmount / 100, 2) }} NOK
                                    </div>
                                    <div style="font-size: 5.5pt; font-weight: 600; color: #111827; margin-top: 1px;">
                                        Netto: {{ number_format(($transaction['amount'] - $refundedAmount) / 100, 2) }} NOK
                                    </div>
                                @endif
                            </td>
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
