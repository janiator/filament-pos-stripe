<?php

namespace App\Services\PowerOffice;

use App\Exceptions\PowerOffice\MissingPowerOfficeMappingException;
use App\Exceptions\PowerOffice\PowerOfficeUnresolvedGlAccountsException;
use App\Models\PosSession;
use App\Models\PowerOfficeIntegration;
use App\Support\PowerOffice\PowerOfficePostingSettings;

final class PowerOfficeSyncPreviewService
{
    public function __construct(
        protected PowerOfficeZReportSync $zReportSync,
        protected PowerOfficeLedgerPayloadBuilder $ledgerPayloadBuilder,
        protected PowerOfficeGeneralLedgerAccountResolver $generalLedgerAccountResolver,
        protected PowerOfficeDepartmentResolver $departmentResolver,
        protected PowerOfficeVatCodeResolver $vatCodeResolver,
        protected PowerOfficeManualVoucherPayloadFactory $manualVoucherPayloadFactory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function previewZReport(
        PosSession $session,
        PowerOfficeIntegration $integration,
        bool $resolvePowerOfficeAccounts = false,
    ): array {
        if ($session->status !== 'closed') {
            return $this->previewError('Session is not closed.', $session->id);
        }

        $zReport = $this->zReportSync->materializeZReportData($session);
        if ($zReport === null) {
            return $this->previewError('No Z-report snapshot (generate Z in POS or Filament first).', $session->id);
        }

        if (! $this->zReportSync->isSessionEligibleForSync($session)) {
            return $this->previewError('Z-report has no transaction value to sync to PowerOffice.', $session->id, $zReport);
        }

        try {
            $payload = $this->ledgerPayloadBuilder->build($session, $integration, $zReport);
            $departmentNo = $payload['department_no'] ?? null;
            if (is_string($departmentNo) && trim($departmentNo) !== '') {
                $departmentId = $this->departmentResolver->resolveIdForDepartmentNo($integration, trim($departmentNo));
                if ($departmentId !== null) {
                    $payload['department_id'] = $departmentId;
                }
            }
        } catch (MissingPowerOfficeMappingException $e) {
            return [
                'ok' => false,
                'kind' => 'z_report',
                'pos_session_id' => $session->id,
                'error' => $e->getMessage(),
                'missing_basis_keys' => $e->missingBasisKeys,
            ];
        } catch (\Throwable $e) {
            return $this->previewError($e->getMessage(), $session->id, $zReport);
        }

        return $this->finishLedgerPreview($payload, $session, $integration, $resolvePowerOfficeAccounts);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function finishLedgerPreview(
        array $payload,
        PosSession $session,
        PowerOfficeIntegration $integration,
        bool $resolvePowerOfficeAccounts,
    ): array {
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
        $differenceMinor = $debitTotal - $creditTotal;

        $out = [
            'ok' => true,
            'kind' => 'z_report',
            'pos_session_id' => $session->id,
            'session_number' => $session->session_number,
            'document_date' => $payload['document_date'] ?? null,
            'description' => $payload['description'] ?? null,
            'currency' => $currencyCode,
            'department_no' => $payload['department_no'] ?? null,
            'lines' => $lines,
            'lines_display' => $this->linesDisplayForLedgerLines($lines, $currencyCode),
            'debit_total_minor' => $debitTotal,
            'credit_total_minor' => $creditTotal,
            'difference_minor' => $differenceMinor,
            'balanced' => $differenceMinor === 0,
            'voucher_posting_mode' => PowerOfficePostingSettings::mode($integration)->value,
            'posts_directly_to_ledger' => PowerOfficePostingSettings::usesDirectPosting($integration),
            'ledger_post_path' => PowerOfficePostingSettings::ledgerPostPath($integration),
            'poweroffice_voucher_payload' => null,
            'resolve_error' => null,
        ];

        if ($resolvePowerOfficeAccounts) {
            if (! $integration->isConnected()) {
                $out['resolve_error'] = 'PowerOffice is not connected; cannot resolve GL account IDs.';

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
                $accountMap = $this->generalLedgerAccountResolver->resolveMapForAccountNos($integration, $accountCodes);
                $zeroVatId = $this->vatCodeResolver->resolveZeroVatId($integration);
                $idempotencyKey = $this->zReportSync->idempotencyKey(
                    (int) $session->effectiveStoreId(),
                    (int) $session->getKey(),
                );
                $out['poweroffice_voucher_payload'] = $this->manualVoucherPayloadFactory->build(
                    $payload,
                    $accountMap,
                    $idempotencyKey,
                    $zeroVatId,
                    PowerOfficePostingSettings::mode($integration),
                );
            } catch (PowerOfficeUnresolvedGlAccountsException $e) {
                $out['resolve_error'] = 'PowerOffice GL accounts not found: '.implode(', ', $e->missingAccountNos);
            } catch (\Throwable $e) {
                $out['resolve_error'] = $e->getMessage();
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $zReport
     * @return array<string, mixed>
     */
    protected function previewError(string $message, int $posSessionId, ?array $zReport = null): array
    {
        return [
            'ok' => false,
            'kind' => 'z_report',
            'pos_session_id' => $posSessionId,
            'error' => $message,
            'z_report' => $zReport,
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
            $rows[] = [
                'account' => (string) ($line['account'] ?? ''),
                'description' => (string) ($line['description'] ?? ''),
                'debit' => round($debitMinor / 100, 2),
                'credit' => round($creditMinor / 100, 2),
                'currency' => $currencyCode,
            ];
        }

        return $rows;
    }
}
