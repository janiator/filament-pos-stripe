<?php

namespace App\Services\PowerOffice;

use App\Enums\PowerOfficeMappingBasis;
use App\Exceptions\PowerOffice\MissingPowerOfficeMappingException;
use App\Models\ConnectedProduct;
use App\Models\PosSession;
use App\Models\PowerOfficeAccountMapping;
use App\Models\PowerOfficeIntegration;
use App\Support\PowerOffice\PowerOfficeLedgerSettings;
use Illuminate\Support\Collection;

class PowerOfficeLedgerPayloadBuilder
{
    public function __construct(
        protected StripeSettlementTotalsForPosSession $stripeSettlementTotals,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(PosSession $session, PowerOfficeIntegration $integration, array $zReport): array
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
            throw new MissingPowerOfficeMappingException($missing);
        }

        $defaultMapping = $this->firstMappingWithPaymentAccounts($integration)
            ?? $this->findMapping($integration, $basis, array_key_first($buckets) ?? '')
            ?? $integration->accountMappings()->where('is_active', true)->orderBy('id')->first();

        if (! $defaultMapping instanceof PowerOfficeAccountMapping) {
            throw new MissingPowerOfficeMappingException([], 'No account mapping rows configured.');
        }

        $lines = [];
        $netAmount = (int) ($zReport['net_amount'] ?? 0);
        $vatAmount = (int) ($zReport['vat_amount'] ?? 0);
        $tipsAmount = (int) ($zReport['total_tips'] ?? 0);

        $bucketTotal = array_sum($buckets);
        if ($bucketTotal <= 0 && $netAmount > 0) {
            $buckets[(string) (int) ($zReport['vat_rate'] ?? 25)] = $netAmount;
            $bucketTotal = $netAmount;
        }

        foreach ($buckets as $basisKey => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $salesAccount = $this->resolveSalesAccountNo($integration, $basis, (string) $basisKey);
            if (! $salesAccount) {
                continue;
            }
            $lines[] = [
                'account' => $salesAccount,
                'debit_minor' => 0,
                'credit_minor' => $amount,
                'description' => 'Z-report '.$session->session_number.' sales ('.$basisKey.')',
            ];
        }

        if ($vatAmount > 0 && $defaultMapping->vat_account_no) {
            $lines[] = [
                'account' => $defaultMapping->vat_account_no,
                'debit_minor' => 0,
                'credit_minor' => $vatAmount,
                'description' => 'Z-report '.$session->session_number.' VAT',
            ];
        }

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

        $lines = array_merge($lines, $this->buildOptionalSettlementLines($session, $integration, $zReport));

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
            'source' => 'positiv_z_report',
            'pos_session_id' => $session->id,
            'session_number' => $session->session_number,
            'document_date' => $closedAt->format('Y-m-d'),
            'description' => 'POS Z-report '.$session->session_number,
            'currency' => 'NOK',
            'lines' => $lines,
        ];
    }

    /**
     * @return list<array{account: string, debit_minor: int, credit_minor: int, description: string}>
     */
    protected function buildOptionalSettlementLines(
        PosSession $session,
        PowerOfficeIntegration $integration,
        array $zReport,
    ): array {
        $extra = [];

        $giftMinor = (int) ($zReport['gift_card_sales_minor'] ?? 0);
        $giftAccount = PowerOfficeLedgerSettings::giftcardLiabilityAccount($integration);
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
            $fee = PowerOfficeLedgerSettings::paymentFeeAccounts($integration);
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
            $po = PowerOfficeLedgerSettings::payoutAccounts($integration);
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
        $rate = (string) (int) ($zReport['vat_rate'] ?? 25);
        $net = (int) ($zReport['net_amount'] ?? 0);

        return $net > 0 ? [$rate => $net] : [];
    }

    /**
     * Split net sales by the product's primary collection (pivot order). Uncategorized products use key "0".
     *
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
        PowerOfficeIntegration $integration,
        PowerOfficeMappingBasis $basis,
        PowerOfficeAccountMapping $fallback,
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
        PowerOfficeIntegration $integration,
        PowerOfficeMappingBasis $basis,
        PowerOfficeAccountMapping $fallback,
        string $method,
    ): ?string {
        $fromSettings = PowerOfficeLedgerSettings::paymentDebitAccount($integration, $method);
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
        PowerOfficeIntegration $integration,
        PowerOfficeMappingBasis $basis,
        string $basisKey,
    ): ?string {
        $row = $this->findMapping($integration, $basis, $basisKey);
        if ($row && filled($row->sales_account_no)) {
            return (string) $row->sales_account_no;
        }

        if ($basis === PowerOfficeMappingBasis::Category || $basis === PowerOfficeMappingBasis::Vendor) {
            return PowerOfficeLedgerSettings::defaultSalesAccount($integration);
        }

        return null;
    }

    protected function findMapping(PowerOfficeIntegration $integration, PowerOfficeMappingBasis $basis, string $basisKey): ?PowerOfficeAccountMapping
    {
        return $integration->accountMappings()
            ->where('basis_type', $basis)
            ->where('basis_key', $basisKey)
            ->where('is_active', true)
            ->first();
    }

    protected function firstMappingWithPaymentAccounts(PowerOfficeIntegration $integration): ?PowerOfficeAccountMapping
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
