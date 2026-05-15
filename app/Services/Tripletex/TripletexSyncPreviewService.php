<?php

namespace App\Services\Tripletex;

use App\Exceptions\Tripletex\MissingTripletexMappingException;
use App\Exceptions\Tripletex\TripletexUnresolvedLedgerAccountsException;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\StoreStripeBalanceTransaction;
use App\Models\StoreStripePayout;
use App\Models\TripletexIntegration;

final class TripletexSyncPreviewService
{
    public function __construct(
        protected TripletexZReportSync $zReportSync,
        protected TripletexZReportLedgerPayloadBuilder $zReportLedger,
        protected TripletexPayoutLedgerPayloadBuilder $payoutLedger,
        protected TripletexPayoutBalanceTransactionHydrator $payoutBalanceTransactionHydrator,
        protected TripletexApiClient $apiClient,
        protected TripletexAccountResolver $accountResolver,
        protected TripletexManualVoucherPayloadFactory $manualVoucherPayloadFactory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function previewZReport(PosSession $session, TripletexIntegration $integration, bool $resolveTripletexAccounts = false): array
    {
        if ($session->status !== 'closed') {
            return $this->previewError('Session is not closed.', 'z_report', $session->id);
        }

        $zReport = $this->zReportSync->materializeZReportData($session);
        if ($zReport === null) {
            return $this->previewError('No Z-report snapshot (generate Z in POS or Filament first).', 'z_report', $session->id);
        }

        if (! $this->zReportSync->isZReportEligibleForSync($zReport)) {
            return $this->previewError('Z-report has no billable amounts for sync.', 'z_report', $session->id, null, $zReport);
        }

        return $this->finishLedgerPreview(
            'z_report',
            $session->id,
            null,
            fn () => $this->zReportLedger->build($session, $integration, $zReport),
            $integration,
            $resolveTripletexAccounts,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function previewPayout(StoreStripePayout $payout, TripletexIntegration $integration, bool $resolveTripletexAccounts = false): array
    {
        if ($payout->status !== 'paid') {
            return $this->previewError('Payout is not in paid status.', 'payout', null, $payout->id);
        }

        $store = $payout->store;
        if (! $store instanceof Store || (int) $store->getKey() !== (int) $integration->store_id) {
            return $this->previewError('Payout does not belong to this integration store.', 'payout', null, $payout->id);
        }

        $mirrorSync = $this->payoutBalanceTransactionHydrator->hydrateIfMissing($store, $payout);

        $preview = $this->finishLedgerPreview(
            'payout',
            null,
            $payout->id,
            fn () => $this->payoutLedger->build($store, $integration, $payout, (bool) $integration->skip_payout_bank_transfer),
            $integration,
            $resolveTripletexAccounts,
        );

        if (($preview['ok'] ?? false) === true) {
            $preview['payout_balance_transaction_sync'] = $mirrorSync;
            $preview['mirror_balance_transaction_count'] = StoreStripeBalanceTransaction::query()
                ->where('store_id', $store->getKey())
                ->where('stripe_payout_id', $payout->stripe_payout_id)
                ->count();
            $preview['payout_external_ticket_sales'] = $this->payoutLedger->externalTicketSalesDiagnostics(
                $store,
                $integration,
                $payout,
            );
        }

        return $preview;
    }

    /**
     * Preview a pre-built ledger payload (e.g. period aggregate: merged lines from many Z or payout previews).
     *
     * @param  array<string, mixed>  $ledgerPayload  Same shape as {@see TripletexZReportLedgerPayloadBuilder::build()} / payout builder: currency, document_date, description, lines.
     * @return array<string, mixed>
     */
    public function previewLedgerPayload(
        array $ledgerPayload,
        TripletexIntegration $integration,
        bool $resolveTripletexAccounts,
        string $kind,
    ): array {
        return $this->finishLedgerPreview(
            $kind,
            null,
            null,
            static fn (): array => $ledgerPayload,
            $integration,
            $resolveTripletexAccounts,
        );
    }

    /**
     * @param  callable(): array<string, mixed>  $buildPayload
     * @return array<string, mixed>
     */
    protected function finishLedgerPreview(
        string $kind,
        ?int $posSessionId,
        ?int $storeStripePayoutId,
        callable $buildPayload,
        TripletexIntegration $integration,
        bool $resolveTripletexAccounts,
    ): array {
        try {
            $payload = $buildPayload();
        } catch (MissingTripletexMappingException $e) {
            return [
                'ok' => false,
                'kind' => $kind,
                'pos_session_id' => $posSessionId,
                'store_stripe_payout_id' => $storeStripePayoutId,
                'error' => $e->getMessage(),
                'missing_basis_keys' => $e->missingBasisKeys,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'kind' => $kind,
                'pos_session_id' => $posSessionId,
                'store_stripe_payout_id' => $storeStripePayoutId,
                'error' => $e->getMessage(),
            ];
        }

        $lines = $payload['lines'] ?? [];
        if (! is_array($lines)) {
            $lines = [];
        }

        $debitTotal = 0;
        $creditTotal = 0;
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $debitTotal += (int) ($line['debit_minor'] ?? 0);
            $creditTotal += (int) ($line['credit_minor'] ?? 0);
        }

        $currencyCode = strtoupper((string) ($payload['currency'] ?? 'NOK'));

        $out = [
            'ok' => true,
            'kind' => $kind,
            'pos_session_id' => $posSessionId,
            'store_stripe_payout_id' => $storeStripePayoutId,
            'document_date' => $payload['document_date'] ?? null,
            'description' => $payload['description'] ?? null,
            'currency' => $currencyCode,
            'lines' => $lines,
            'lines_display' => $this->linesDisplayForLedgerLines($lines, $currencyCode),
            'debit_total_minor' => $debitTotal,
            'credit_total_minor' => $creditTotal,
            'balanced' => $debitTotal === $creditTotal,
            'tripletex_voucher_payload' => null,
            'tripletex_postings_display' => null,
            'resolve_error' => null,
        ];

        if ($kind === 'payout' && array_key_exists('skip_payout_bank_transfer', $payload)) {
            $out['skip_payout_bank_transfer'] = (bool) $payload['skip_payout_bank_transfer'];
        }

        if ($resolveTripletexAccounts) {
            if (! $integration->isConnected()) {
                $out['resolve_error'] = 'Tripletex is not connected; cannot resolve ledger account IDs.';

                return $out;
            }

            $accountCodes = [];
            foreach ($lines as $line) {
                if (is_array($line) && filled($line['account'] ?? null)) {
                    $accountCodes[] = trim((string) $line['account']);
                }
            }
            $accountCodes = array_values(array_unique($accountCodes));

            try {
                $sessionToken = $this->apiClient->createSessionToken($integration);
                $accountMap = $this->accountResolver->resolveMapForAccountNos($integration, $sessionToken, $accountCodes);
                $voucherPayload = $this->manualVoucherPayloadFactory->build($payload, $accountMap);
                $out['tripletex_voucher_payload'] = $voucherPayload;
                $out['tripletex_postings_display'] = $this->postingsDisplayForVoucherPayload($voucherPayload);
            } catch (TripletexUnresolvedLedgerAccountsException $e) {
                $out['resolve_error'] = 'Tripletex ledger accounts not found: '.implode(', ', $e->missingAccountNos);
            } catch (\Throwable $e) {
                $out['resolve_error'] = $e->getMessage();
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $z_report
     * @return array<string, mixed>
     */
    protected function previewError(string $message, string $kind, ?int $posSessionId, ?int $storeStripePayoutId = null, ?array $z_report = null): array
    {
        return [
            'ok' => false,
            'kind' => $kind,
            'pos_session_id' => $posSessionId,
            'store_stripe_payout_id' => $storeStripePayoutId,
            'error' => $message,
            'z_report' => $z_report,
        ];
    }

    /**
     * @param  array<int, mixed>  $lines
     * @return list<array<string, mixed>>
     */
    protected function linesDisplayForLedgerLines(array $lines, string $currencyCode): array
    {
        $rows = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $debitMinor = (int) ($line['debit_minor'] ?? 0);
            $creditMinor = (int) ($line['credit_minor'] ?? 0);
            $row = [
                'account' => (string) ($line['account'] ?? ''),
                'description' => (string) ($line['description'] ?? ''),
                'debit' => round($debitMinor / 100, 2),
                'credit' => round($creditMinor / 100, 2),
                'currency' => $currencyCode,
            ];
            if (filled($line['posting_date'] ?? null)) {
                $row['posting_date'] = (string) $line['posting_date'];
            }
            if (filled($line['line_kind'] ?? null)) {
                $row['line_kind'] = (string) $line['line_kind'];
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $voucherPayload
     * @return list<array<string, mixed>>
     */
    protected function postingsDisplayForVoucherPayload(array $voucherPayload): array
    {
        $postings = $voucherPayload['postings'] ?? [];
        if (! is_array($postings)) {
            return [];
        }

        $rows = [];
        foreach ($postings as $posting) {
            if (! is_array($posting)) {
                continue;
            }
            $account = $posting['account'] ?? [];
            $account = is_array($account) ? $account : [];

            $rows[] = [
                'row' => $posting['row'] ?? null,
                'account_number' => $account['number'] ?? null,
                'account_name' => $account['name'] ?? null,
                'amount_gross' => $posting['amountGross'] ?? null,
                'description' => (string) ($posting['description'] ?? ''),
                'date' => $posting['date'] ?? null,
            ];
        }

        return $rows;
    }
}
