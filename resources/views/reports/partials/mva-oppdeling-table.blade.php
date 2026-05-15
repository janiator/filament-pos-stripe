{{-- VAT breakdown: aggregate row, or per standard rate (0 / 15 / 25 %) when line-item split is on the report. --}}
@php
    $splitNet = \App\Support\PowerOffice\PowerOfficeStandardVatRates::normalizeSalesNetMinorByVatRateMap($report['sales_net_minor_by_vat_rate'] ?? null);
    $splitVat = \App\Support\PowerOffice\PowerOfficeStandardVatRates::normalizeVatMinorByVatRateMap($report['vat_minor_by_vat_rate'] ?? null);
    $useVatRateSplit = $splitNet !== [];
    $tableClass = $tableClass ?? null;
@endphp
<table @if($tableClass) class="{{ $tableClass }}" @endif>
    @if($useVatRateSplit)
        <tr>
            <th>Sats</th>
            <th>MVA-grunnlag</th>
            <th class="text-right">MVA-beløp</th>
            <th class="text-right">Totalt (inkl. MVA)</th>
        </tr>
        @foreach(\App\Support\PowerOffice\PowerOfficeStandardVatRates::basisKeys() as $rateKey)
            @php
                $netMinor = (int) ($splitNet[$rateKey] ?? 0);
            @endphp
            @if($netMinor <= 0)
                @continue
            @endif
            @php
                $vatPartMinor = (int) ($splitVat[$rateKey] ?? 0);
                if ($rateKey === '0') {
                    $basisMinor = $netMinor;
                    $grossMinor = $netMinor;
                } else {
                    $basisMinor = $netMinor;
                    $grossMinor = $netMinor + $vatPartMinor;
                }
                $rateLabel = \App\Support\PowerOffice\PowerOfficeStandardVatRates::options()[$rateKey] ?? ($rateKey.'%');
            @endphp
            <tr>
                <td>{{ $rateLabel }}</td>
                <td>{{ number_format($basisMinor / 100, 2) }} NOK</td>
                <td class="text-right">{{ number_format($vatPartMinor / 100, 2) }} NOK</td>
                <td class="text-right">{{ number_format($grossMinor / 100, 2) }} NOK</td>
            </tr>
        @endforeach
        @php
            $sumBasis = (int) ($report['vat_base'] ?? 0);
            $sumVat = (int) ($report['vat_amount'] ?? 0);
            $sumGross = $sumBasis + $sumVat;
        @endphp
        <tr style="font-weight: 600; border-top: 2px solid #e5e7eb;">
            <th scope="row">Sum</th>
            <td>{{ number_format($sumBasis / 100, 2) }} NOK</td>
            <td class="text-right">{{ number_format($sumVat / 100, 2) }} NOK</td>
            <td class="text-right">{{ number_format($sumGross / 100, 2) }} NOK</td>
        </tr>
    @else
        <tr>
            <th>MVA-grunnlag</th>
            <th>MVA-beløp ({{ $report['vat_rate'] ?? 25 }}%)</th>
            <th class="text-right">Totalt (inkl. MVA)</th>
        </tr>
        <tr>
            <td>{{ number_format(($report['vat_base'] ?? 0) / 100, 2) }} NOK</td>
            <td>{{ number_format(($report['vat_amount'] ?? 0) / 100, 2) }} NOK</td>
            @php
                $vatInclTotalMinor = (int) ($report['vat_base'] ?? 0) + (int) ($report['vat_amount'] ?? 0);
            @endphp
            <td class="text-right">{{ number_format($vatInclTotalMinor / 100, 2) }} NOK</td>
        </tr>
    @endif
</table>
