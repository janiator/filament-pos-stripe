<?php

namespace App\Services\Tripletex;

use App\Models\PosSession;
use App\Models\Store;
use App\Models\StoreStripePayout;
use App\Models\TripletexIntegration;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class TripletexPeriodPreviewService
{
    public function __construct(
        protected TripletexSyncPreviewService $previewService,
        protected TripletexZReportSync $zReportSync,
    ) {}

    /**
     * Build read-only previews for every closed session (Z) and paid payout in the period, aligned with historical sync date filters.
     *
     * @return array<string, mixed>
     */
    public function previewPeriod(
        Store $store,
        TripletexIntegration $integration,
        CarbonInterface $from,
        CarbonInterface $to,
        bool $resolveTripletexAccounts = false,
        int $maxZReports = 100,
        int $maxPayouts = 100,
        bool $detailedPreviews = false,
    ): array {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->endOfDay();

        $maxZReports = max(1, min($maxZReports, 500));
        $maxPayouts = max(1, min($maxPayouts, 500));

        $zBase = PosSession::query()
            ->where('store_id', $store->getKey())
            ->where('status', 'closed')
            ->whereNotNull('closing_data')
            ->where('closed_at', '>=', $from)
            ->where('closed_at', '<=', $to);

        $zTotal = (clone $zBase)->count();
        $zSessions = (clone $zBase)
            ->orderByDesc('closed_at')
            ->limit($maxZReports)
            ->get();

        $zRows = [];
        $zRawPreviews = [];
        foreach ($zSessions as $session) {
            if (! $session instanceof PosSession) {
                continue;
            }
            $preview = $this->previewService->previewZReport($session, $integration, $resolveTripletexAccounts);
            $zRawPreviews[] = $preview;
            $zRows[] = [
                'pos_session_id' => $session->id,
                'closed_at' => $session->closed_at?->toIso8601String(),
                'eligible_for_sync' => $this->zReportSync->isSessionEligibleForSync($session),
                'preview' => $this->shapePreviewForPeriod($preview, $detailedPreviews),
            ];
        }

        $payoutBase = StoreStripePayout::query()
            ->where('store_id', $store->getKey())
            ->where('status', 'paid')
            ->where('arrival_date', '>=', $from)
            ->where('arrival_date', '<=', $to);

        $payoutTotal = (clone $payoutBase)->count();
        $payouts = (clone $payoutBase)
            ->orderByDesc('arrival_date')
            ->limit($maxPayouts)
            ->get();

        $pRows = [];
        $pRawPreviews = [];
        foreach ($payouts as $payout) {
            if (! $payout instanceof StoreStripePayout) {
                continue;
            }
            $preview = $this->previewService->previewPayout($payout, $integration, $resolveTripletexAccounts);
            $pRawPreviews[] = $preview;
            $pRows[] = [
                'store_stripe_payout_id' => $payout->id,
                'stripe_payout_id' => $payout->stripe_payout_id,
                'arrival_date' => $payout->arrival_date?->toIso8601String(),
                'preview' => $this->shapePreviewForPeriod($preview, $detailedPreviews),
            ];
        }

        return [
            'ok' => true,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'store_id' => (int) $store->getKey(),
            ],
            'limits' => [
                'max_z_reports' => $maxZReports,
                'max_payouts' => $maxPayouts,
                'z_reports_total_in_period' => $zTotal,
                'payouts_total_in_period' => $payoutTotal,
                'z_reports_truncated' => $zTotal > $maxZReports,
                'payouts_truncated' => $payoutTotal > $maxPayouts,
            ],
            'z_reports' => $zRows,
            'payouts' => $pRows,
            'rollup' => $this->buildRollup($zRows, $pRows),
            'aggregate_vouchers' => [
                'z_reports' => $this->buildAggregateVoucherPreview(
                    $zRawPreviews,
                    'aggregate_z_report',
                    $to->toDateString(),
                    'Period aggregate — all Z-reports in range (preview only, not postable)',
                    $integration,
                    $resolveTripletexAccounts,
                    $detailedPreviews,
                ),
                'payouts' => $this->buildAggregateVoucherPreview(
                    $pRawPreviews,
                    'aggregate_payout',
                    $to->toDateString(),
                    'Period aggregate — all payouts in range (preview only, not postable)',
                    $integration,
                    $resolveTripletexAccounts,
                    $detailedPreviews,
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    protected function shapePreviewForPeriod(array $preview, bool $detailed): array
    {
        if ($detailed) {
            return $preview;
        }

        if (($preview['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'kind' => $preview['kind'] ?? null,
                'error' => $preview['error'] ?? 'Preview failed',
                'missing_basis_keys' => $preview['missing_basis_keys'] ?? null,
            ];
        }

        $out = [
            'ok' => true,
            'kind' => $preview['kind'] ?? null,
            'document_date' => $preview['document_date'] ?? null,
            'description' => $preview['description'] ?? null,
            'currency' => $preview['currency'] ?? null,
            'balanced' => (bool) ($preview['balanced'] ?? false),
            'debit_total_minor' => (int) ($preview['debit_total_minor'] ?? 0),
            'credit_total_minor' => (int) ($preview['credit_total_minor'] ?? 0),
            'line_kinds' => $this->lineKindTotalsFromLines($preview['lines'] ?? []),
            'resolve_error' => $preview['resolve_error'] ?? null,
        ];

        if (($preview['kind'] ?? '') === 'payout') {
            $out['payout_balance_transaction_sync'] = $preview['payout_balance_transaction_sync'] ?? null;
            $out['mirror_balance_transaction_count'] = $preview['mirror_balance_transaction_count'] ?? null;
            $out['payout_external_ticket_sales'] = $preview['payout_external_ticket_sales'] ?? null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, array{debit_minor: int, credit_minor: int}>
     */
    protected function lineKindsFromPreviewShape(array $preview): array
    {
        $fromCompact = $preview['line_kinds'] ?? null;
        if (is_array($fromCompact) && $fromCompact !== []) {
            return $fromCompact;
        }

        return $this->lineKindTotalsFromLines($preview['lines'] ?? []);
    }

    /**
     * @param  array<int, mixed>  $lines
     * @return array<string, array{debit_minor: int, credit_minor: int}>
     */
    protected function lineKindTotalsFromLines(array $lines): array
    {
        $by = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $kind = filled($line['line_kind'] ?? null)
                ? (string) $line['line_kind']
                : 'unspecified';
            if (! isset($by[$kind])) {
                $by[$kind] = ['debit_minor' => 0, 'credit_minor' => 0];
            }
            $by[$kind]['debit_minor'] += (int) ($line['debit_minor'] ?? 0);
            $by[$kind]['credit_minor'] += (int) ($line['credit_minor'] ?? 0);
        }

        return $by;
    }

    /**
     * @param  list<array<string, mixed>>  $zRows
     * @param  list<array<string, mixed>>  $payoutRows
     * @return array<string, mixed>
     */
    protected function buildRollup(array $zRows, array $payoutRows): array
    {
        $zOk = 0;
        $zFail = 0;
        $zDebit = 0;
        $zCredit = 0;
        $lineKindsZ = [];

        foreach ($zRows as $row) {
            $p = $row['preview'] ?? [];
            if (($p['ok'] ?? false) === true) {
                $zOk++;
                $zDebit += (int) ($p['debit_total_minor'] ?? 0);
                $zCredit += (int) ($p['credit_total_minor'] ?? 0);
                $this->mergeLineKinds($lineKindsZ, $this->lineKindsFromPreviewShape($p));
            } else {
                $zFail++;
            }
        }

        $pOk = 0;
        $pFail = 0;
        $pDebit = 0;
        $pCredit = 0;
        $lineKindsPayout = [];
        $extMatched = 0;
        $extWebCandidates = 0;

        foreach ($payoutRows as $row) {
            $p = $row['preview'] ?? [];
            if (($p['ok'] ?? false) === true) {
                $pOk++;
                $pDebit += (int) ($p['debit_total_minor'] ?? 0);
                $pCredit += (int) ($p['credit_total_minor'] ?? 0);
                $this->mergeLineKinds($lineKindsPayout, $this->lineKindsFromPreviewShape($p));
                $ext = $p['payout_external_ticket_sales'] ?? null;
                if (is_array($ext)) {
                    $extMatched += (int) ($ext['matched_for_voucher_lines'] ?? 0);
                    $extWebCandidates += (int) ($ext['charges_without_pos_session'] ?? 0);
                }
            } else {
                $pFail++;
            }
        }

        $externalTicketLines = $lineKindsPayout['external_ticket_sales'] ?? ['debit_minor' => 0, 'credit_minor' => 0];
        $externalClearing = $lineKindsPayout['external_ticket_clearing'] ?? ['debit_minor' => 0, 'credit_minor' => 0];

        return [
            'z_reports' => [
                'preview_rows' => count($zRows),
                'ok' => $zOk,
                'failed' => $zFail,
                'debit_total_minor' => $zDebit,
                'credit_total_minor' => $zCredit,
                'line_kinds' => $lineKindsZ,
            ],
            'payouts' => [
                'preview_rows' => count($payoutRows),
                'ok' => $pOk,
                'failed' => $pFail,
                'debit_total_minor' => $pDebit,
                'credit_total_minor' => $pCredit,
                'line_kinds' => $lineKindsPayout,
            ],
            'external_ticket_sales' => [
                'matched_charges_count_across_payouts' => $extMatched,
                'charges_without_pos_session_count_across_payouts' => $extWebCandidates,
                'external_ticket_sales_credit_minor' => (int) ($externalTicketLines['credit_minor'] ?? 0),
                'external_ticket_sales_debit_minor' => (int) ($externalTicketLines['debit_minor'] ?? 0),
                'external_ticket_clearing_credit_minor' => (int) ($externalClearing['credit_minor'] ?? 0),
                'external_ticket_clearing_debit_minor' => (int) ($externalClearing['debit_minor'] ?? 0),
            ],
            'interpretation' => 'POS ticket revenue is posted on Z-report vouchers when a session closes. Web/advance ticket lines that match your external-ticket rules appear on payout vouchers (see payout diagnostics per row). Compare Z and payout totals separately; they are not additive ticket revenue.',
        ];
    }

    /**
     * @param  array<string, array{debit_minor: int, credit_minor: int}>  $into
     * @param  array<string, array{debit_minor: int, credit_minor: int}>  $from
     */
    protected function mergeLineKinds(array &$into, array $from): void
    {
        foreach ($from as $kind => $totals) {
            if (! is_array($totals)) {
                continue;
            }
            if (! isset($into[$kind])) {
                $into[$kind] = ['debit_minor' => 0, 'credit_minor' => 0];
            }
            $into[$kind]['debit_minor'] += (int) ($totals['debit_minor'] ?? 0);
            $into[$kind]['credit_minor'] += (int) ($totals['credit_minor'] ?? 0);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rawPreviews  Full outputs from {@see TripletexSyncPreviewService::previewZReport()} or {@see TripletexSyncPreviewService::previewPayout()}.
     * @return array<string, mixed>
     */
    protected function buildAggregateVoucherPreview(
        array $rawPreviews,
        string $ledgerKind,
        string $documentDate,
        string $description,
        TripletexIntegration $integration,
        bool $resolveTripletexAccounts,
        bool $detailedPreviews,
    ): array {
        $successful = 0;
        foreach ($rawPreviews as $p) {
            if (is_array($p) && ($p['ok'] ?? false) === true) {
                $successful++;
            }
        }
        $merged = $this->mergeLedgerLinesFromSuccessfulPreviews($rawPreviews);

        $disclaimer = 'Illustrative merged ledger lines only. Actual Tripletex posting remains one voucher per closed session (Z) or per paid payout; do not post this aggregate as a single voucher.';

        if ($merged === []) {
            $message = $ledgerKind === 'aggregate_z_report'
                ? 'No successful Z-report previews in this period to aggregate.'
                : 'No successful payout previews in this period to aggregate.';

            return [
                'ok' => false,
                'kind' => $ledgerKind,
                'source_previews_count' => count($rawPreviews),
                'successful_previews_count' => $successful,
                'error' => $message,
                'preview' => null,
                'lines_display' => [],
                'tripletex_voucher_payload' => null,
                'tripletex_postings_display' => [],
                'resolve_error' => null,
                'disclaimer' => $disclaimer,
            ];
        }

        $currency = 'NOK';
        foreach ($rawPreviews as $p) {
            if (! is_array($p)) {
                continue;
            }
            if (($p['ok'] ?? false) === true && filled($p['currency'] ?? null)) {
                $currency = strtoupper((string) $p['currency']);
                break;
            }
        }

        $ledgerPayload = [
            'source' => $ledgerKind,
            'document_date' => $documentDate,
            'description' => $description,
            'currency' => $currency,
            'lines' => $merged,
        ];

        $full = $this->previewService->previewLedgerPayload(
            $ledgerPayload,
            $integration,
            $resolveTripletexAccounts,
            $ledgerKind,
        );

        if (($full['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'kind' => $ledgerKind,
                'source_previews_count' => count($rawPreviews),
                'successful_previews_count' => $successful,
                'error' => (string) ($full['error'] ?? 'Aggregate preview failed.'),
                'preview' => null,
                'lines_display' => [],
                'tripletex_voucher_payload' => null,
                'tripletex_postings_display' => [],
                'resolve_error' => null,
                'disclaimer' => $disclaimer,
            ];
        }

        return [
            'ok' => true,
            'kind' => $ledgerKind,
            'source_previews_count' => count($rawPreviews),
            'successful_previews_count' => $successful,
            'error' => null,
            'preview' => $this->shapePreviewForPeriod($full, $detailedPreviews),
            'lines_display' => is_array($full['lines_display'] ?? null) ? $full['lines_display'] : [],
            'tripletex_voucher_payload' => $full['tripletex_voucher_payload'] ?? null,
            'tripletex_postings_display' => is_array($full['tripletex_postings_display'] ?? null) ? $full['tripletex_postings_display'] : [],
            'resolve_error' => $full['resolve_error'] ?? null,
            'disclaimer' => $disclaimer,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rawPreviews
     * @return list<array<string, mixed>>
     */
    protected function mergeLedgerLinesFromSuccessfulPreviews(array $rawPreviews): array
    {
        $byKey = [];
        foreach ($rawPreviews as $preview) {
            if (! is_array($preview) || ($preview['ok'] ?? false) !== true) {
                continue;
            }
            $lines = $preview['lines'] ?? [];
            if (! is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $key = $this->mergeKeyForLedgerLine($line);
                if (! isset($byKey[$key])) {
                    $byKey[$key] = $this->cloneLineForAggregateMergeTemplate($line);
                    $byKey[$key]['debit_minor'] = 0;
                    $byKey[$key]['credit_minor'] = 0;
                }
                $byKey[$key]['debit_minor'] += (int) ($line['debit_minor'] ?? 0);
                $byKey[$key]['credit_minor'] += (int) ($line['credit_minor'] ?? 0);
            }
        }

        $out = [];
        foreach ($byKey as $line) {
            $netted = $this->netDebitCreditOnMergedLine($line);
            if ($netted !== null) {
                $out[] = $netted;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function mergeKeyForLedgerLine(array $line): string
    {
        $account = trim((string) ($line['account'] ?? ''));
        $vat = isset($line['tripletex_vat_type_id']) && is_numeric($line['tripletex_vat_type_id'])
            ? (string) (int) $line['tripletex_vat_type_id']
            : '';
        $supplier = isset($line['tripletex_supplier_id']) && is_numeric($line['tripletex_supplier_id'])
            ? (string) (int) $line['tripletex_supplier_id']
            : '';
        $kind = (string) ($line['line_kind'] ?? '');

        return $account."\t".$vat."\t".$supplier."\t".$kind;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    protected function cloneLineForAggregateMergeTemplate(array $line): array
    {
        $copy = $line;
        unset($copy['posting_date']);

        return $copy;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>|null
     */
    protected function netDebitCreditOnMergedLine(array $line): ?array
    {
        $debit = (int) ($line['debit_minor'] ?? 0);
        $credit = (int) ($line['credit_minor'] ?? 0);
        if ($debit <= 0 && $credit <= 0) {
            return null;
        }
        if ($debit > 0 && $credit > 0) {
            if ($debit >= $credit) {
                $debit -= $credit;
                $credit = 0;
            } else {
                $credit -= $debit;
                $debit = 0;
            }
        }
        if ($debit <= 0 && $credit <= 0) {
            return null;
        }
        $line['debit_minor'] = $debit;
        $line['credit_minor'] = $credit;

        return $line;
    }
}
