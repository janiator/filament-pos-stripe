<?php

namespace App\Services\Tripletex;

use App\Enums\PowerOfficeMappingBasis;
use App\Exceptions\Tripletex\MissingTripletexMappingException;
use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PosSession;
use App\Models\TripletexAccountMapping;
use App\Models\TripletexIntegration;
use App\Services\PowerOffice\StripeSettlementTotalsForPosSession;
use App\Support\Tripletex\TripletexLedgerSettings;
use Illuminate\Support\Collection;

class TripletexZReportLedgerPayloadBuilder
{
    public function __construct(
        protected StripeSettlementTotalsForPosSession $stripeSettlementTotals,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(PosSession $session, TripletexIntegration $integration, array $zReport): array
    {
        $basis = $integration->mapping_basis;
        $buckets = $this->extractBuckets($session, $basis, $zReport);

        $missing = [];
        foreach (array_keys($buckets) as $key) {
            if (! $this->resolveSalesAccountNo($integration, $basis, (string) $key)) {
                $missing[] = (string) $key;
            }
        }
        if ($missing !== []) {
            throw new MissingTripletexMappingException($missing);
        }

        $defaultMapping = $this->firstMappingWithPaymentAccounts($integration)
            ?? $this->findMapping($integration, $basis, array_key_first($buckets) ?? '')
            ?? $integration->accountMappings()->where('is_active', true)->orderBy('id')->first();

        if (! $defaultMapping instanceof TripletexAccountMapping) {
            throw new MissingTripletexMappingException([], 'No Tripletex account mapping rows configured.');
        }

        $netAmount = (int) ($zReport['net_amount'] ?? 0);
        $vatAmount = (int) ($zReport['vat_amount'] ?? 0);
        $tipsAmount = (int) ($zReport['total_tips'] ?? 0);

        $bucketTotal = array_sum($buckets);
        if ($bucketTotal <= 0 && $netAmount > 0) {
            $split = $zReport['sales_net_minor_by_vat_rate'] ?? null;
            if (is_array($split) && $split !== []) {
                foreach ($split as $key => $val) {
                    $k = (string) (int) (string) $key;
                    $n = (int) $val;
                    if ($n > 0) {
                        $buckets[$k] = $n;
                    }
                }
            } else {
                $buckets[(string) (int) ($zReport['vat_rate'] ?? 25)] = $netAmount;
            }
        }

        $dayBundle = $this->sessionChargeDayWeights($session);
        $splitByDay = TripletexLedgerSettings::zReportSplitLinesByCalendarDay($integration)
            && is_array($dayBundle);

        if ($splitByDay) {
            $lines = $this->buildLedgerLinesWithCalendarDaySplit(
                $session,
                $integration,
                $zReport,
                $basis,
                $defaultMapping,
                $buckets,
                $vatAmount,
                $tipsAmount,
                $dayBundle,
            );
        } else {
            $lines = $this->buildLedgerLinesWithoutDaySplit(
                $session,
                $integration,
                $zReport,
                $basis,
                $defaultMapping,
                $buckets,
                $vatAmount,
                $tipsAmount,
            );
        }

        $debitSum = array_sum(array_column($lines, 'debit_minor'));
        $creditSum = array_sum(array_column($lines, 'credit_minor'));
        $diff = $debitSum - $creditSum;
        if ($diff !== 0 && $defaultMapping->rounding_account_no) {
            if ($diff > 0) {
                $lines[] = [
                    'account' => $defaultMapping->rounding_account_no,
                    'debit_minor' => 0,
                    'credit_minor' => $diff,
                    'description' => 'Rounding Z-report '.$session->session_number,
                ];
            } else {
                $lines[] = [
                    'account' => $defaultMapping->rounding_account_no,
                    'debit_minor' => abs($diff),
                    'credit_minor' => 0,
                    'description' => 'Rounding Z-report '.$session->session_number,
                ];
            }
        }

        $closedAt = $session->closed_at ?? now();

        return [
            'source' => 'positiv_z_report_tripletex',
            'pos_session_id' => $session->id,
            'session_number' => $session->session_number,
            'document_date' => $closedAt->format('Y-m-d'),
            'description' => 'POS Z-report '.$session->session_number,
            'currency' => $this->currencyForSession($session),
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<string, int>  $buckets
     * @return list<array<string, mixed>>
     */
    protected function buildLedgerLinesWithoutDaySplit(
        PosSession $session,
        TripletexIntegration $integration,
        array $zReport,
        PowerOfficeMappingBasis $basis,
        TripletexAccountMapping $defaultMapping,
        array $buckets,
        int $vatAmount,
        int $tipsAmount,
    ): array {
        $lines = [];

        foreach ($buckets as $basisKey => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $salesAccount = $this->resolveSalesAccountNo($integration, $basis, (string) $basisKey);
            if (! $salesAccount) {
                continue;
            }
            $line = [
                'account' => $salesAccount,
                'debit_minor' => 0,
                'credit_minor' => $amount,
                'description' => 'Z-report '.$session->session_number.' sales ('.$basisKey.')',
            ];
            $vatTypeId = TripletexLedgerSettings::tripletexVatTypeIdForSalesBasisKey($integration, (string) $basisKey);
            if ($vatTypeId !== null) {
                $line['tripletex_vat_type_id'] = $vatTypeId;
            }
            $lines[] = $line;
        }

        $this->appendZReportVatCreditLines(
            $lines,
            $session,
            $integration,
            $defaultMapping,
            $zReport,
            $vatAmount,
            null,
        );

        if ($tipsAmount > 0 && $defaultMapping->tips_account_no) {
            $lines[] = [
                'account' => $defaultMapping->tips_account_no,
                'debit_minor' => 0,
                'credit_minor' => $tipsAmount,
                'description' => 'Z-report '.$session->session_number.' tips',
            ];
        }

        $paymentLines = $this->paymentDebitLines($zReport, $integration, $basis, $defaultMapping, $buckets);
        $lines = array_merge($lines, $paymentLines);

        if ($integration->z_report_include_settlement) {
            $lines = array_merge($lines, $this->buildOptionalSettlementLines($session, $integration, $zReport));
        }

        return $lines;
    }

    /**
     * @param  array<string, int>  $buckets
     * @param  array{by_day_total: array<string, int>, by_day_by_method: array<string, array<string, int>>}  $dayBundle
     * @return list<array<string, mixed>>
     */
    protected function buildLedgerLinesWithCalendarDaySplit(
        PosSession $session,
        TripletexIntegration $integration,
        array $zReport,
        PowerOfficeMappingBasis $basis,
        TripletexAccountMapping $defaultMapping,
        array $buckets,
        int $vatAmount,
        int $tipsAmount,
        array $dayBundle,
    ): array {
        $lines = [];
        $byDayTotal = $dayBundle['by_day_total'] ?? [];
        if (! is_array($byDayTotal) || array_sum($byDayTotal) <= 0) {
            return $this->buildLedgerLinesWithoutDaySplit(
                $session,
                $integration,
                $zReport,
                $basis,
                $defaultMapping,
                $buckets,
                $vatAmount,
                $tipsAmount,
            );
        }

        foreach ($buckets as $basisKey => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $salesAccount = $this->resolveSalesAccountNo($integration, $basis, (string) $basisKey);
            if (! $salesAccount) {
                continue;
            }
            $vatTypeId = TripletexLedgerSettings::tripletexVatTypeIdForSalesBasisKey($integration, (string) $basisKey);
            foreach ($this->allocateIntegerAcrossDays($amount, $byDayTotal) as $day => $slice) {
                if ($slice <= 0) {
                    continue;
                }
                $line = [
                    'account' => $salesAccount,
                    'debit_minor' => 0,
                    'credit_minor' => $slice,
                    'posting_date' => $day,
                    'description' => 'Z-report '.$session->session_number.' sales ('.$basisKey.') '.$day,
                ];
                if ($vatTypeId !== null) {
                    $line['tripletex_vat_type_id'] = $vatTypeId;
                }
                $lines[] = $line;
            }
        }

        $vatByRate = $zReport['vat_minor_by_vat_rate'] ?? null;
        if (is_array($vatByRate) && $vatByRate !== [] && $defaultMapping->vat_account_no) {
            foreach ($vatByRate as $rateKey => $vatPart) {
                $vatPart = (int) $vatPart;
                if ($vatPart <= 0) {
                    continue;
                }
                $outVat = TripletexLedgerSettings::tripletexVatTypeIdForSalesBasisKey($integration, (string) $rateKey)
                    ?? TripletexLedgerSettings::tripletexVatTypeOutputVat($integration);
                foreach ($this->allocateIntegerAcrossDays($vatPart, $byDayTotal) as $day => $slice) {
                    if ($slice <= 0) {
                        continue;
                    }
                    $vatLine = [
                        'account' => $defaultMapping->vat_account_no,
                        'debit_minor' => 0,
                        'credit_minor' => $slice,
                        'posting_date' => $day,
                        'description' => 'Z-report '.$session->session_number.' VAT '.$rateKey.'% '.$day,
                    ];
                    if ($outVat !== null) {
                        $vatLine['tripletex_vat_type_id'] = $outVat;
                    }
                    $lines[] = $vatLine;
                }
            }
        } elseif ($vatAmount > 0 && $defaultMapping->vat_account_no) {
            $outVat = TripletexLedgerSettings::tripletexVatTypeOutputVat($integration);
            foreach ($this->allocateIntegerAcrossDays($vatAmount, $byDayTotal) as $day => $slice) {
                if ($slice <= 0) {
                    continue;
                }
                $vatLine = [
                    'account' => $defaultMapping->vat_account_no,
                    'debit_minor' => 0,
                    'credit_minor' => $slice,
                    'posting_date' => $day,
                    'description' => 'Z-report '.$session->session_number.' VAT '.$day,
                ];
                if ($outVat !== null) {
                    $vatLine['tripletex_vat_type_id'] = $outVat;
                }
                $lines[] = $vatLine;
            }
        }

        if ($tipsAmount > 0 && $defaultMapping->tips_account_no) {
            foreach ($this->allocateIntegerAcrossDays($tipsAmount, $byDayTotal) as $day => $slice) {
                if ($slice <= 0) {
                    continue;
                }
                $lines[] = [
                    'account' => $defaultMapping->tips_account_no,
                    'debit_minor' => 0,
                    'credit_minor' => $slice,
                    'posting_date' => $day,
                    'description' => 'Z-report '.$session->session_number.' tips '.$day,
                ];
            }
        }

        $lines = array_merge($lines, $this->paymentDebitLinesSplitByDay(
            $zReport,
            $integration,
            $basis,
            $defaultMapping,
            $buckets,
            $dayBundle,
        ));

        if ($integration->z_report_include_settlement) {
            $lines = array_merge($lines, $this->buildOptionalSettlementLinesSplitByDay(
                $session,
                $integration,
                $zReport,
                $byDayTotal,
            ));
        }

        $this->reconcileCalendarSplitLinesPerPostingDate(
            $lines,
            $integration,
            $basis,
            $defaultMapping,
            (string) $session->session_number,
        );

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function appendZReportVatCreditLines(
        array &$lines,
        PosSession $session,
        TripletexIntegration $integration,
        TripletexAccountMapping $defaultMapping,
        array $zReport,
        int $vatAmount,
        ?string $postingDate,
    ): void {
        $vatByRate = $zReport['vat_minor_by_vat_rate'] ?? null;
        if (is_array($vatByRate) && $vatByRate !== [] && $defaultMapping->vat_account_no) {
            foreach ($vatByRate as $rateKey => $vatPart) {
                $vatPart = (int) $vatPart;
                if ($vatPart <= 0) {
                    continue;
                }
                $outVat = TripletexLedgerSettings::tripletexVatTypeIdForSalesBasisKey($integration, (string) $rateKey)
                    ?? TripletexLedgerSettings::tripletexVatTypeOutputVat($integration);
                $vatLine = [
                    'account' => $defaultMapping->vat_account_no,
                    'debit_minor' => 0,
                    'credit_minor' => $vatPart,
                    'description' => 'Z-report '.$session->session_number.' VAT '.$rateKey.'%',
                ];
                if ($postingDate !== null) {
                    $vatLine['posting_date'] = $postingDate;
                }
                if ($outVat !== null) {
                    $vatLine['tripletex_vat_type_id'] = $outVat;
                }
                $lines[] = $vatLine;
            }

            return;
        }

        if ($vatAmount > 0 && $defaultMapping->vat_account_no) {
            $vatLine = [
                'account' => $defaultMapping->vat_account_no,
                'debit_minor' => 0,
                'credit_minor' => $vatAmount,
                'description' => 'Z-report '.$session->session_number.' VAT',
            ];
            if ($postingDate !== null) {
                $vatLine['posting_date'] = $postingDate;
            }
            $outVat = TripletexLedgerSettings::tripletexVatTypeOutputVat($integration);
            if ($outVat !== null) {
                $vatLine['tripletex_vat_type_id'] = $outVat;
            }
            $lines[] = $vatLine;
        }
    }

    /**
     * Tripletex validates that postings for each {@see posting_date} sum to zero. Splitting sales,
     * VAT, tips, and payment debits with separate {@see allocateIntegerAcrossDays} passes leaves
     * small per-day remainder differences; absorb them on payment debit lines for that date.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    protected function reconcileCalendarSplitLinesPerPostingDate(
        array &$lines,
        TripletexIntegration $integration,
        PowerOfficeMappingBasis $basis,
        TripletexAccountMapping $fallback,
        string $sessionNumber,
    ): void {
        $cardAccount = $this->resolvePaymentDebitAccount($integration, $basis, $fallback, 'card');
        $cashAccount = $this->resolvePaymentDebitAccount($integration, $basis, $fallback, 'cash');

        $byDate = [];
        foreach ($lines as $idx => $line) {
            $d = $line['posting_date'] ?? null;
            if (! is_string($d) || $d === '') {
                continue;
            }
            $byDate[$d][] = $idx;
        }

        foreach ($byDate as $date => $indices) {
            $credit = 0;
            $debit = 0;
            foreach ($indices as $i) {
                $credit += (int) ($lines[$i]['credit_minor'] ?? 0);
                $debit += (int) ($lines[$i]['debit_minor'] ?? 0);
            }
            $delta = $credit - $debit;
            if ($delta === 0) {
                continue;
            }

            if ($delta > 0) {
                $this->bumpPaymentDebitMinorOnSplitDate(
                    $lines,
                    $indices,
                    $delta,
                    $cardAccount,
                    $cashAccount,
                    $date,
                    $sessionNumber,
                );
            } else {
                $this->reducePaymentDebitMinorOnSplitDate(
                    $lines,
                    $indices,
                    -$delta,
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<int>  $indices
     */
    protected function bumpPaymentDebitMinorOnSplitDate(
        array &$lines,
        array $indices,
        int $delta,
        ?string $cardAccount,
        ?string $cashAccount,
        string $date,
        string $sessionNumber,
    ): void {
        foreach ([$cardAccount, $cashAccount] as $account) {
            if (! $account) {
                continue;
            }
            foreach ($indices as $i) {
                if (($lines[$i]['account'] ?? '') !== $account) {
                    continue;
                }
                if ((int) ($lines[$i]['debit_minor'] ?? 0) <= 0) {
                    continue;
                }
                $lines[$i]['debit_minor'] = (int) $lines[$i]['debit_minor'] + $delta;

                return;
            }
        }

        foreach ($indices as $i) {
            $desc = (string) ($lines[$i]['description'] ?? '');
            if (! str_contains($desc, 'Z-report payment ')) {
                continue;
            }
            if ((int) ($lines[$i]['debit_minor'] ?? 0) <= 0) {
                continue;
            }
            $lines[$i]['debit_minor'] = (int) $lines[$i]['debit_minor'] + $delta;

            return;
        }

        if ($cardAccount) {
            $lines[] = [
                'account' => $cardAccount,
                'debit_minor' => $delta,
                'credit_minor' => 0,
                'posting_date' => $date,
                'description' => 'Z-report '.$sessionNumber.' payment (split rounding) '.$date,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<int>  $indices
     */
    protected function reducePaymentDebitMinorOnSplitDate(
        array &$lines,
        array $indices,
        int $delta,
    ): void {
        $paymentIndices = [];
        foreach ($indices as $i) {
            $desc = (string) ($lines[$i]['description'] ?? '');
            if (! str_contains($desc, 'Z-report payment ')) {
                continue;
            }
            if ((int) ($lines[$i]['debit_minor'] ?? 0) <= 0) {
                continue;
            }
            $paymentIndices[] = $i;
        }

        usort($paymentIndices, function (int $a, int $b) use ($lines): int {
            return ((int) ($lines[$b]['debit_minor'] ?? 0)) <=> ((int) ($lines[$a]['debit_minor'] ?? 0));
        });

        $remaining = $delta;
        foreach ($paymentIndices as $i) {
            if ($remaining <= 0) {
                return;
            }
            $d = (int) ($lines[$i]['debit_minor'] ?? 0);
            if ($d <= 0) {
                continue;
            }
            $take = min($d, $remaining);
            $lines[$i]['debit_minor'] = $d - $take;
            $remaining -= $take;
        }
    }

    /**
     * @param  array<string, int>  $weightsByDate  date (Y-m-d) => positive weight
     * @return array<string, int> date => allocated minor units (sums to $total)
     */
    protected function allocateIntegerAcrossDays(int $total, array $weightsByDate): array
    {
        if ($total <= 0 || $weightsByDate === []) {
            return [];
        }
        $sumW = array_sum($weightsByDate);
        if ($sumW <= 0) {
            return [];
        }
        $dates = array_keys($weightsByDate);
        sort($dates, SORT_STRING);
        $out = [];
        $assigned = 0;
        $n = count($dates);
        foreach ($dates as $i => $day) {
            $w = (int) ($weightsByDate[$day] ?? 0);
            if ($i === $n - 1) {
                $out[$day] = max(0, $total - $assigned);
            } else {
                $slice = (int) floor($total * $w / $sumW);
                $out[$day] = $slice;
                $assigned += $slice;
            }
        }

        return $out;
    }

    /**
     * Weights per calendar day for splitting the aggregated non-cash clearing line
     * (net_card + net_mobile + net_other under VAT basis without by_payment_method_net).
     *
     * Using only {@see $byMethod}['card'] misses card_present/mobile/vipps/etc., which then
     * incorrectly fell back to {@see $byDayTotal} and put card clearing on cash-only days —
     * Tripletex rejects vouchers where postings for a given line date do not sum to zero.
     *
     * @param  array<string, array<string, int>>  $byMethod
     * @param  array<string, int>  $byDayTotal
     * @return array<string, int>
     */
    protected function nonCashPaymentSplitWeights(array $byMethod, array $byDayTotal): array
    {
        $merged = [];
        foreach ($byMethod as $method => $weights) {
            if ($method === 'cash' || ! is_array($weights)) {
                continue;
            }
            foreach ($weights as $day => $v) {
                $n = (int) $v;
                if ($n <= 0) {
                    continue;
                }
                $merged[$day] = ($merged[$day] ?? 0) + $n;
            }
        }
        if (array_sum($merged) > 0) {
            return $merged;
        }

        $cash = $byMethod['cash'] ?? [];
        $cash = is_array($cash) ? $cash : [];
        foreach ($byDayTotal as $day => $total) {
            $rest = max(0, (int) $total - (int) ($cash[$day] ?? 0));
            if ($rest > 0) {
                $merged[$day] = $rest;
            }
        }
        if (array_sum($merged) > 0) {
            return $merged;
        }

        return $byDayTotal;
    }

    /**
     * @return array{by_day_total: array<string, int>, by_day_by_method: array<string, array<string, int>>}|null
     */
    protected function sessionChargeDayWeights(PosSession $session): ?array
    {
        $charges = ConnectedCharge::query()
            ->where('pos_session_id', $session->id)
            ->where('status', 'succeeded')
            ->get(['paid_at', 'created_at', 'amount', 'amount_refunded', 'application_fee_amount', 'payment_method']);

        if ($charges->isEmpty()) {
            return null;
        }

        $tz = (string) config('app.timezone');
        $byDayTotal = [];
        $byDayByMethod = [];

        foreach ($charges as $c) {
            $ts = $c->paid_at ?? $c->created_at;
            if ($ts === null) {
                continue;
            }
            $day = $ts->copy()->timezone($tz)->format('Y-m-d');
            $net = max(0, (int) $c->amount - (int) $c->amount_refunded - (int) ($c->application_fee_amount ?? 0));
            if ($net <= 0) {
                continue;
            }
            $byDayTotal[$day] = ($byDayTotal[$day] ?? 0) + $net;
            $m = $this->normalizeChargePaymentMethodForZ($c->payment_method);
            if (! isset($byDayByMethod[$m])) {
                $byDayByMethod[$m] = [];
            }
            $byDayByMethod[$m][$day] = ($byDayByMethod[$m][$day] ?? 0) + $net;
        }

        if (array_sum($byDayTotal) <= 0) {
            return null;
        }

        return [
            'by_day_total' => $byDayTotal,
            'by_day_by_method' => $byDayByMethod,
        ];
    }

    protected function normalizeChargePaymentMethodForZ(?string $paymentMethod): string
    {
        $m = strtolower(trim((string) $paymentMethod));
        if ($m === '' || $m === 'unknown') {
            return 'card';
        }
        if ($m === 'cash') {
            return 'cash';
        }
        if (str_contains($m, 'vipps')) {
            return 'vipps';
        }
        if (str_contains($m, 'apple') || str_contains($m, 'google') || $m === 'mobile' || $m === 'link') {
            return 'mobile';
        }
        if ($m === 'gift_token' || str_contains($m, 'gift')) {
            return 'gift_token';
        }
        if ($m === 'card_present') {
            return 'card_present';
        }

        return in_array($m, ['card', 'card_present', 'mobile', 'vipps', 'gift_token'], true) ? $m : 'card';
    }

    /**
     * @param  array{by_day_total: array<string, int>, by_day_by_method: array<string, array<string, int>>}  $dayBundle
     * @param  array<string, int>  $buckets
     * @return list<array<string, mixed>>
     */
    protected function paymentDebitLinesSplitByDay(
        array $zReport,
        TripletexIntegration $integration,
        PowerOfficeMappingBasis $basis,
        TripletexAccountMapping $fallback,
        array $buckets,
        array $dayBundle,
    ): array {
        $lines = [];
        $byDayTotal = $dayBundle['by_day_total'] ?? [];
        $byMethod = $dayBundle['by_day_by_method'] ?? [];

        $byNet = $zReport['by_payment_method_net'] ?? [];
        if ($byNet instanceof Collection) {
            $byNet = $byNet->all();
        }

        if (is_array($byNet) && $byNet !== []) {
            foreach ($byNet as $method => $data) {
                if (! is_array($data)) {
                    continue;
                }
                $amount = (int) ($data['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $methodKey = (string) $method;
                $weights = (is_array($byMethod[$methodKey] ?? null) && array_sum($byMethod[$methodKey]) > 0)
                    ? $byMethod[$methodKey]
                    : $byDayTotal;
                $account = $this->resolvePaymentDebitAccount($integration, $basis, $fallback, $methodKey);
                if (! $account) {
                    continue;
                }
                foreach ($this->allocateIntegerAcrossDays($amount, $weights) as $day => $slice) {
                    if ($slice <= 0) {
                        continue;
                    }
                    $lines[] = [
                        'account' => $account,
                        'debit_minor' => $slice,
                        'credit_minor' => 0,
                        'posting_date' => $day,
                        'description' => 'Z-report payment '.$methodKey.' '.$day,
                    ];
                }
            }

            return $lines;
        }

        if ($basis === PowerOfficeMappingBasis::PaymentMethod) {
            $by = $zReport['by_payment_method'] ?? [];
            if ($by instanceof Collection) {
                $by = $by->all();
            }
            if (is_array($by)) {
                foreach ($by as $method => $data) {
                    if (! is_array($data)) {
                        continue;
                    }
                    $amount = (int) ($data['amount'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }
                    $methodKey = (string) $method;
                    $weights = (is_array($byMethod[$methodKey] ?? null) && array_sum($byMethod[$methodKey]) > 0)
                        ? $byMethod[$methodKey]
                        : $byDayTotal;
                    $account = $this->resolvePaymentDebitAccount($integration, $basis, $fallback, $methodKey);
                    if (! $account) {
                        continue;
                    }
                    foreach ($this->allocateIntegerAcrossDays($amount, $weights) as $day => $slice) {
                        if ($slice <= 0) {
                            continue;
                        }
                        $lines[] = [
                            'account' => $account,
                            'debit_minor' => $slice,
                            'credit_minor' => 0,
                            'posting_date' => $day,
                            'description' => 'Z-report payment '.$methodKey.' '.$day,
                        ];
                    }
                }
            }

            return $lines;
        }

        $netCash = (int) ($zReport['net_cash_amount'] ?? 0);
        $netCard = (int) ($zReport['net_card_amount'] ?? 0);
        $netMobile = (int) ($zReport['net_mobile_amount'] ?? 0);
        $netOther = (int) ($zReport['net_other_amount'] ?? 0);

        $map = [
            'cash' => $netCash,
            'card' => $netCard + $netMobile + $netOther,
        ];

        foreach ($map as $method => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $weights = $method === 'card'
                ? $this->nonCashPaymentSplitWeights($byMethod, $byDayTotal)
                : ((is_array($byMethod[$method] ?? null) && array_sum($byMethod[$method]) > 0)
                    ? $byMethod[$method]
                    : $byDayTotal);
            $account = $this->resolvePaymentDebitAccount($integration, $basis, $fallback, $method);
            if (! $account) {
                continue;
            }
            foreach ($this->allocateIntegerAcrossDays($amount, $weights) as $day => $slice) {
                if ($slice <= 0) {
                    continue;
                }
                $lines[] = [
                    'account' => $account,
                    'debit_minor' => $slice,
                    'credit_minor' => 0,
                    'posting_date' => $day,
                    'description' => 'Z-report payment '.$method.' '.$day,
                ];
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, int>  $byDayTotal
     * @return list<array<string, mixed>>
     */
    protected function buildOptionalSettlementLinesSplitByDay(
        PosSession $session,
        TripletexIntegration $integration,
        array $zReport,
        array $byDayTotal,
    ): array {
        $extra = [];

        $giftMinor = (int) ($zReport['gift_card_sales_minor'] ?? 0);
        $giftAccount = TripletexLedgerSettings::giftcardLiabilityAccount($integration);
        if ($giftMinor > 0 && $giftAccount) {
            foreach ($this->allocateIntegerAcrossDays($giftMinor, $byDayTotal) as $day => $slice) {
                if ($slice <= 0) {
                    continue;
                }
                $extra[] = [
                    'account' => $giftAccount,
                    'debit_minor' => 0,
                    'credit_minor' => $slice,
                    'posting_date' => $day,
                    'description' => 'Z-report '.$session->session_number.' gift card sales (liability) '.$day,
                ];
            }
        }

        $fees = (int) ($zReport['stripe_fees_minor'] ?? 0);
        if ($fees <= 0) {
            $fees = $this->stripeSettlementTotals->feesMinorForSession($session);
        }
        if ($fees > 0) {
            $fee = TripletexLedgerSettings::paymentFeeAccounts($integration);
            if ($fee['credit'] && $fee['debit']) {
                foreach ($this->allocateIntegerAcrossDays($fees, $byDayTotal) as $day => $slice) {
                    if ($slice <= 0) {
                        continue;
                    }
                    $extra[] = [
                        'account' => $fee['credit'],
                        'debit_minor' => 0,
                        'credit_minor' => $slice,
                        'posting_date' => $day,
                        'description' => 'Z-report '.$session->session_number.' payment fees (settlement) '.$day,
                    ];
                    $extra[] = [
                        'account' => $fee['debit'],
                        'debit_minor' => $slice,
                        'credit_minor' => 0,
                        'posting_date' => $day,
                        'description' => 'Z-report '.$session->session_number.' payment fees (expense) '.$day,
                    ];
                }
            }
        }

        $payout = (int) ($zReport['payout_to_bank_minor'] ?? 0);
        if ($payout <= 0) {
            $payout = $this->stripeSettlementTotals->payoutMinorForSessionCloseDate($session);
        }
        if ($payout > 0) {
            $po = TripletexLedgerSettings::payoutAccounts($integration);
            if ($po['credit'] && $po['debit']) {
                foreach ($this->allocateIntegerAcrossDays($payout, $byDayTotal) as $day => $slice) {
                    if ($slice <= 0) {
                        continue;
                    }
                    $extra[] = [
                        'account' => $po['credit'],
                        'debit_minor' => 0,
                        'credit_minor' => $slice,
                        'posting_date' => $day,
                        'description' => 'Z-report '.$session->session_number.' payout (settlement) '.$day,
                    ];
                    $extra[] = [
                        'account' => $po['debit'],
                        'debit_minor' => $slice,
                        'credit_minor' => 0,
                        'posting_date' => $day,
                        'description' => 'Z-report '.$session->session_number.' payout (bank) '.$day,
                    ];
                }
            }
        }

        return $extra;
    }

    protected function currencyForSession(PosSession $session): string
    {
        return 'NOK';
    }

    /**
     * @return list<array{account: string, debit_minor: int, credit_minor: int, description: string}>
     */
    protected function buildOptionalSettlementLines(
        PosSession $session,
        TripletexIntegration $integration,
        array $zReport,
    ): array {
        $extra = [];

        $giftMinor = (int) ($zReport['gift_card_sales_minor'] ?? 0);
        $giftAccount = TripletexLedgerSettings::giftcardLiabilityAccount($integration);
        if ($giftMinor > 0 && $giftAccount) {
            $extra[] = [
                'account' => $giftAccount,
                'debit_minor' => 0,
                'credit_minor' => $giftMinor,
                'description' => 'Z-report '.$session->session_number.' gift card sales (liability)',
            ];
        }

        $fees = (int) ($zReport['stripe_fees_minor'] ?? 0);
        if ($fees <= 0) {
            $fees = $this->stripeSettlementTotals->feesMinorForSession($session);
        }
        if ($fees > 0) {
            $fee = TripletexLedgerSettings::paymentFeeAccounts($integration);
            if ($fee['credit'] && $fee['debit']) {
                $extra[] = [
                    'account' => $fee['credit'],
                    'debit_minor' => 0,
                    'credit_minor' => $fees,
                    'description' => 'Z-report '.$session->session_number.' payment fees (settlement)',
                ];
                $extra[] = [
                    'account' => $fee['debit'],
                    'debit_minor' => $fees,
                    'credit_minor' => 0,
                    'description' => 'Z-report '.$session->session_number.' payment fees (expense)',
                ];
            }
        }

        $payout = (int) ($zReport['payout_to_bank_minor'] ?? 0);
        if ($payout <= 0) {
            $payout = $this->stripeSettlementTotals->payoutMinorForSessionCloseDate($session);
        }
        if ($payout > 0) {
            $po = TripletexLedgerSettings::payoutAccounts($integration);
            if ($po['credit'] && $po['debit']) {
                $extra[] = [
                    'account' => $po['credit'],
                    'debit_minor' => 0,
                    'credit_minor' => $payout,
                    'description' => 'Z-report '.$session->session_number.' payout (settlement)',
                ];
                $extra[] = [
                    'account' => $po['debit'],
                    'debit_minor' => $payout,
                    'credit_minor' => 0,
                    'description' => 'Z-report '.$session->session_number.' payout (bank)',
                ];
            }
        }

        return $extra;
    }

    /**
     * @return array<string, int>
     */
    protected function extractBuckets(PosSession $session, PowerOfficeMappingBasis $basis, array $zReport): array
    {
        return match ($basis) {
            PowerOfficeMappingBasis::Vat => $this->bucketsForVat($zReport),
            PowerOfficeMappingBasis::Category => $this->bucketsForCollection($session, $zReport),
            PowerOfficeMappingBasis::Vendor => $this->bucketsForVendor($zReport),
            PowerOfficeMappingBasis::PaymentMethod => $this->bucketsForPaymentMethod($zReport),
        };
    }

    /**
     * @return array<string, int>
     */
    protected function bucketsForVat(array $zReport): array
    {
        $split = $zReport['sales_net_minor_by_vat_rate'] ?? null;
        if (is_array($split) && $split !== []) {
            $buckets = [];
            foreach ($split as $key => $val) {
                $k = (string) (int) (string) $key;
                $n = (int) $val;
                if ($n > 0) {
                    $buckets[$k] = $n;
                }
            }
            if ($buckets !== []) {
                return $buckets;
            }
        }

        $rate = (string) (int) ($zReport['vat_rate'] ?? 25);
        $net = (int) ($zReport['net_amount'] ?? 0);

        return $net > 0 ? [$rate => $net] : [];
    }

    /**
     * @return array<string, int>
     */
    protected function bucketsForCollection(PosSession $session, array $zReport): array
    {
        $productsSold = $zReport['products_sold'] ?? [];
        if (! is_array($productsSold) || $productsSold === []) {
            return $this->bucketsForVat($zReport);
        }

        $productIds = [];
        foreach ($productsSold as $row) {
            if (! empty($row['product_id'])) {
                $productIds[] = (int) $row['product_id'];
            }
        }
        $productIds = array_values(array_unique($productIds));

        $products = ConnectedProduct::query()
            ->whereIn('id', $productIds)
            ->with(['collections' => fn ($q) => $q->orderBy('collection_product.sort_order')])
            ->get()
            ->keyBy('id');

        $buckets = [];
        foreach ($productsSold as $row) {
            $amount = (int) ($row['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $pid = isset($row['product_id']) ? (int) $row['product_id'] : null;
            $collectionKey = '0';
            if ($pid && $products->has($pid)) {
                $firstCollection = $products[$pid]->collections->first();
                $collectionKey = $firstCollection ? (string) $firstCollection->getKey() : '0';
            }
            $buckets[$collectionKey] = ($buckets[$collectionKey] ?? 0) + $amount;
        }

        return $buckets;
    }

    /**
     * @return array<string, int>
     */
    protected function bucketsForVendor(array $zReport): array
    {
        $rows = $zReport['sales_by_vendor'] ?? [];
        if ($rows instanceof Collection) {
            $rows = $rows->all();
        }
        if (! is_array($rows) || $rows === []) {
            return $this->bucketsForVat($zReport);
        }

        $buckets = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = isset($row['id']) ? (string) $row['id'] : 'unknown';
            $amount = (int) ($row['amount'] ?? 0);
            if ($amount > 0) {
                $buckets[$id] = ($buckets[$id] ?? 0) + $amount;
            }
        }

        return $buckets;
    }

    /**
     * @return array<string, int>
     */
    protected function bucketsForPaymentMethod(array $zReport): array
    {
        $by = $zReport['by_payment_method'] ?? [];
        if ($by instanceof Collection) {
            $by = $by->all();
        }
        if (! is_array($by) || $by === []) {
            return $this->bucketsForVat($zReport);
        }

        $buckets = [];
        foreach ($by as $method => $data) {
            if (! is_array($data)) {
                continue;
            }
            $amount = (int) ($data['amount'] ?? 0);
            if ($amount > 0) {
                $buckets[(string) $method] = $amount;
            }
        }

        return $buckets;
    }

    /**
     * @param  array<string, int>  $buckets
     * @return list<array{account: string, debit_minor: int, credit_minor: int, description: string}>
     */
    protected function paymentDebitLines(
        array $zReport,
        TripletexIntegration $integration,
        PowerOfficeMappingBasis $basis,
        TripletexAccountMapping $fallback,
        array $buckets,
    ): array {
        $lines = [];

        $byNet = $zReport['by_payment_method_net'] ?? [];
        if ($byNet instanceof Collection) {
            $byNet = $byNet->all();
        }

        if (is_array($byNet) && $byNet !== []) {
            foreach ($byNet as $method => $data) {
                if (! is_array($data)) {
                    continue;
                }
                $amount = (int) ($data['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $account = $this->resolvePaymentDebitAccount($integration, $basis, $fallback, (string) $method);
                if (! $account) {
                    continue;
                }
                $lines[] = [
                    'account' => $account,
                    'debit_minor' => $amount,
                    'credit_minor' => 0,
                    'description' => 'Z-report payment '.(string) $method,
                ];
            }

            return $lines;
        }

        if ($basis === PowerOfficeMappingBasis::PaymentMethod) {
            $by = $zReport['by_payment_method'] ?? [];
            if ($by instanceof Collection) {
                $by = $by->all();
            }
            if (is_array($by)) {
                foreach ($by as $method => $data) {
                    if (! is_array($data)) {
                        continue;
                    }
                    $amount = (int) ($data['amount'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }
                    $account = $this->resolvePaymentDebitAccount($integration, $basis, $fallback, (string) $method);
                    if (! $account) {
                        continue;
                    }
                    $lines[] = [
                        'account' => $account,
                        'debit_minor' => $amount,
                        'credit_minor' => 0,
                        'description' => 'Z-report payment '.(string) $method,
                    ];
                }
            }

            return $lines;
        }

        $netCash = (int) ($zReport['net_cash_amount'] ?? 0);
        $netCard = (int) ($zReport['net_card_amount'] ?? 0);
        $netMobile = (int) ($zReport['net_mobile_amount'] ?? 0);
        $netOther = (int) ($zReport['net_other_amount'] ?? 0);

        $map = [
            'cash' => $netCash,
            'card' => $netCard + $netMobile + $netOther,
        ];

        foreach ($map as $method => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $account = $this->resolvePaymentDebitAccount($integration, $basis, $fallback, $method);
            if (! $account) {
                continue;
            }
            $lines[] = [
                'account' => $account,
                'debit_minor' => $amount,
                'credit_minor' => 0,
                'description' => 'Z-report payment '.$method,
            ];
        }

        return $lines;
    }

    protected function resolvePaymentDebitAccount(
        TripletexIntegration $integration,
        PowerOfficeMappingBasis $basis,
        TripletexAccountMapping $fallback,
        string $method,
    ): ?string {
        $fromSettings = TripletexLedgerSettings::paymentDebitAccount($integration, $method);
        if ($fromSettings) {
            return $fromSettings;
        }

        if ($basis === PowerOfficeMappingBasis::PaymentMethod) {
            $mapping = $this->findMapping($integration, $basis, $method) ?? $fallback;
            $isCash = $method === 'cash';

            return $isCash
                ? ($mapping->cash_account_no ?? $fallback->cash_account_no)
                : ($mapping->card_clearing_account_no ?? $fallback->card_clearing_account_no);
        }

        $mapping = $fallback;

        return $method === 'cash'
            ? ($mapping->cash_account_no ?? null)
            : ($mapping->card_clearing_account_no ?? null);
    }

    protected function resolveSalesAccountNo(
        TripletexIntegration $integration,
        PowerOfficeMappingBasis $basis,
        string $basisKey,
    ): ?string {
        $row = $this->findMapping($integration, $basis, $basisKey);
        if ($row && filled($row->sales_account_no)) {
            return (string) $row->sales_account_no;
        }

        if ($basis === PowerOfficeMappingBasis::Category || $basis === PowerOfficeMappingBasis::Vendor) {
            return TripletexLedgerSettings::defaultSalesAccount($integration);
        }

        return null;
    }

    protected function findMapping(TripletexIntegration $integration, PowerOfficeMappingBasis $basis, string $basisKey): ?TripletexAccountMapping
    {
        return $integration->accountMappings()
            ->where('basis_type', $basis)
            ->where('basis_key', $basisKey)
            ->where('is_active', true)
            ->first();
    }

    protected function firstMappingWithPaymentAccounts(TripletexIntegration $integration): ?TripletexAccountMapping
    {
        return $integration->accountMappings()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNotNull('cash_account_no')
                    ->orWhereNotNull('card_clearing_account_no');
            })
            ->orderBy('id')
            ->first();
    }
}
