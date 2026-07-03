<?php

namespace App\Services\PowerOffice;

use App\Enums\PowerOfficeMappingBasis;
use App\Exceptions\PowerOffice\MissingPowerOfficeMappingException;
use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\ArticleGroupCode;
use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PosSession;
use App\Models\PowerOfficeAccountMapping;
use App\Models\PowerOfficeIntegration;
use App\Models\Vendor;
use App\Support\PowerOffice\PowerOfficeLedgerLineDescriptions;
use App\Support\PowerOffice\PowerOfficeLedgerSettings;
use App\Support\PowerOffice\PowerOfficeStandardVatRates;
use Illuminate\Support\Collection;

class PowerOfficeLedgerPayloadBuilder
{
    /** @var int Commission minor units held until flushed to one 3023 line. */
    protected int $pendingAggregatedCommissionMinor = 0;

    protected ?string $pendingAggregatedCommissionAccount = null;

    public function __construct(
        protected StripeSettlementTotalsForPosSession $stripeSettlementTotals,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(PosSession $session, PowerOfficeIntegration $integration, array $zReport): array
    {
        $this->pendingAggregatedCommissionMinor = 0;
        $this->pendingAggregatedCommissionAccount = null;

        $basis = $integration->mapping_basis;
        $mappingBuckets = $this->extractBuckets($session, $basis, $zReport);

        if ($basis === PowerOfficeMappingBasis::Category) {
            $missing = $this->validateHybridCategoryMappings($session, $integration, $zReport);
        } else {
            $missing = [];
            foreach (array_keys($mappingBuckets) as $key) {
                if (! $this->resolveSalesAccountNo($integration, $basis, (string) $key, $session)) {
                    $missing[] = (string) $key;
                }
            }
        }
        if ($missing !== []) {
            throw new MissingPowerOfficeMappingException($missing);
        }

        $defaultMapping = $this->firstMappingWithPaymentAccounts($integration)
            ?? $this->findMapping($integration, $basis, array_key_first($mappingBuckets) ?? '')
            ?? $integration->accountMappings()->where('is_active', true)->orderBy('id')->first();

        if (! $defaultMapping instanceof PowerOfficeAccountMapping) {
            throw new MissingPowerOfficeMappingException([], 'No account mapping rows configured.');
        }

        $lines = [];
        $tipsAmount = (int) ($zReport['total_tips'] ?? 0);

        // Sales are credited GROSS with the GL account's vat code (PowerOffice splits out the VAT),
        // exactly like a manually entered voucher. No explicit output-VAT line is posted.
        if ($basis === PowerOfficeMappingBasis::Category) {
            $lines = array_merge($lines, $this->buildHybridCollectionSalesCreditLines($session, $integration, $zReport));
        } elseif ($basis === PowerOfficeMappingBasis::Vendor) {
            $lines = array_merge($lines, $this->buildVendorSalesCreditLines($session, $integration, $zReport));
        } else {
            $salesBuckets = PowerOfficeStandardVatRates::resolveGrossSalesCreditBucketsForLedger($basis, $zReport, $mappingBuckets);

            foreach ($salesBuckets as $basisKey => $amount) {
                if ($amount <= 0) {
                    continue;
                }
                $salesAccount = $this->resolveSalesAccountNo($integration, $basis, (string) $basisKey, $session);
                if (! $salesAccount) {
                    continue;
                }
                $lines[] = $this->salesCreditLine(
                    $salesAccount,
                    $amount,
                    $this->salesDescriptionForBasisKey($integration, $basis, (string) $basisKey),
                );
            }
        }

        if ($tipsAmount > 0 && $defaultMapping->tips_account_no) {
            $lines[] = [
                'account' => $defaultMapping->tips_account_no,
                'debit_minor' => 0,
                'credit_minor' => $tipsAmount,
                'description' => PowerOfficeLedgerLineDescriptions::tips(),
            ];
        }

        $paymentLines = $this->paymentDebitLines($session, $zReport, $integration, $basis, $defaultMapping, $mappingBuckets);
        $lines = array_merge($lines, $paymentLines);

        $lines = array_merge($lines, $this->buildVippsFeeLines($session, $integration));
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
                    'description' => PowerOfficeLedgerLineDescriptions::rounding(),
                ];
            } else {
                $lines[] = [
                    'account' => $defaultMapping->rounding_account_no,
                    'debit_minor' => abs($diff),
                    'credit_minor' => 0,
                    'description' => PowerOfficeLedgerLineDescriptions::rounding(),
                ];
            }
        }

        $closedAt = $session->closed_at ?? now();

        return [
            'source' => 'positiv_z_report',
            'pos_session_id' => $session->id,
            'session_number' => $session->session_number,
            'document_date' => $closedAt->format('Y-m-d'),
            'description' => PowerOfficeLedgerLineDescriptions::voucher($session),
            'currency' => 'NOK',
            'department_no' => PowerOfficeLedgerSettings::departmentNo($integration),
            'lines' => $lines,
        ];
    }

    /**
     * @return array{account: string, debit_minor: int, credit_minor: int, description: string}
     */
    protected function salesCreditLine(string $account, int $creditMinor, string $description): array
    {
        return [
            'account' => $account,
            'debit_minor' => 0,
            'credit_minor' => $creditMinor,
            'description' => $description,
        ];
    }

    /**
     * @param  array<string, int>  $credits
     */
    protected function accumulateCredit(array &$credits, string $account, int $amount, string $description): void
    {
        if ($amount <= 0) {
            return;
        }

        $key = $account."\0".$description;
        $credits[$key] = ($credits[$key] ?? 0) + $amount;
    }

    /**
     * @param  array<string, int>  $credits
     * @return list<array{account: string, debit_minor: int, credit_minor: int, description: string}>
     */
    protected function creditLinesFromAccumulatedMap(array $credits): array
    {
        $lines = [];
        foreach ($credits as $key => $creditMinor) {
            if ($creditMinor <= 0) {
                continue;
            }
            [$account, $description] = explode("\0", $key, 2);
            $lines[] = $this->salesCreditLine($account, $creditMinor, $description);
        }

        return $lines;
    }

    protected function salesDescriptionForHybridProduct(?ConnectedProduct $product): string
    {
        if ($product !== null) {
            $product->loadMissing(['articleGroupCode', 'collections']);
            $articleName = trim((string) ($product->articleGroupCode?->name ?? ''));
            if ($articleName !== '') {
                return PowerOfficeLedgerLineDescriptions::categorySales($articleName);
            }

            $collectionName = trim((string) ($product->collections->first()?->name ?? ''));
            if ($collectionName !== '') {
                return PowerOfficeLedgerLineDescriptions::categorySales($collectionName);
            }
        }

        return PowerOfficeLedgerLineDescriptions::categorySales('');
    }

    protected function salesDescriptionForBasisKey(
        PowerOfficeIntegration $integration,
        PowerOfficeMappingBasis $basis,
        string $basisKey,
    ): string {
        return match ($basis) {
            PowerOfficeMappingBasis::Vat => PowerOfficeLedgerLineDescriptions::vatRateSales($basisKey),
            PowerOfficeMappingBasis::Category => PowerOfficeLedgerLineDescriptions::categorySales(
                ProductCollection::query()->find($basisKey)?->name ?? $basisKey,
            ),
            PowerOfficeMappingBasis::ArticleGroup => PowerOfficeLedgerLineDescriptions::categorySales(
                ArticleGroupCode::query()
                    ->where('store_id', $integration->store_id)
                    ->where('code', $basisKey)
                    ->value('name') ?? $basisKey,
            ),
            PowerOfficeMappingBasis::Vendor => is_numeric($basisKey)
                ? PowerOfficeLedgerLineDescriptions::vendorNameFromRow(
                    Vendor::query()->find((int) $basisKey)?->name,
                )
                : PowerOfficeLedgerLineDescriptions::categorySales(''),
            default => PowerOfficeLedgerLineDescriptions::categorySales($basisKey),
        };
    }

    /**
     * @return list<string>
     */
    protected function validateHybridCategoryMappings(
        PosSession $session,
        PowerOfficeIntegration $integration,
        array $zReport,
    ): array {
        $productsSold = $this->productsSoldForHybridSales($session, $zReport);
        if (! is_array($productsSold) || $productsSold === []) {
            $missing = [];
            foreach (array_keys($this->bucketsForCollection($session, $zReport)) as $key) {
                if (! $this->resolveSalesAccountNo($integration, PowerOfficeMappingBasis::Category, (string) $key, $session)) {
                    $missing[] = (string) $key;
                }
            }

            return $missing;
        }

        $products = $this->loadProductsForHybridSales($productsSold);
        $missing = [];

        foreach ($productsSold as $row) {
            $amount = (int) ($row['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $product = $this->resolveProductFromSoldRow($row, $products);
            $vendor = $product?->vendor;

            if ($vendor && (float) ($vendor->commission_percent ?? 0) > 0) {
                if (! $this->resolveVendorSupplierAccount($integration, $vendor)) {
                    $missing[] = 'vendor:'.$vendor->getKey();
                }

                continue;
            }

            if (! $this->resolveHybridSalesAccount($integration, $product, $session)) {
                $articleKey = $this->articleGroupKeyForProduct($product);
                $missing[] = filled($articleKey)
                    ? 'article_group:'.$articleKey
                    : $this->collectionKeyForProduct($product);
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @return list<array{account: string, debit_minor: int, credit_minor: int, description: string}>
     */
    protected function buildHybridCollectionSalesCreditLines(
        PosSession $session,
        PowerOfficeIntegration $integration,
        array $zReport,
    ): array {
        $vendorRows = $zReport['sales_by_vendor'] ?? [];
        if ($vendorRows instanceof Collection) {
            $vendorRows = $vendorRows->all();
        }
        if (is_array($vendorRows) && $vendorRows !== []) {
            return $this->buildHybridSalesCreditLinesFromSalesByVendor($session, $integration, $zReport, $vendorRows);
        }

        $productsSold = $this->productsSoldForHybridSales($session, $zReport);
        if (! is_array($productsSold) || $productsSold === []) {
            // Without product rows the collection buckets fall back to net-per-VAT-rate; convert to gross.
            $grossBuckets = PowerOfficeStandardVatRates::resolveGrossSalesCreditBucketsForLedger(
                PowerOfficeMappingBasis::Vat,
                $zReport,
                $this->bucketsForVat($zReport),
            );

            $lines = [];
            foreach ($grossBuckets as $collectionKey => $amount) {
                if ($amount <= 0) {
                    continue;
                }
                $account = $this->resolveSalesAccountNo($integration, PowerOfficeMappingBasis::Category, (string) $collectionKey, $session);
                if (! $account) {
                    continue;
                }
                $lines[] = $this->salesCreditLine(
                    $account,
                    $amount,
                    PowerOfficeLedgerLineDescriptions::categorySales(
                        ProductCollection::query()->find($collectionKey)?->name ?? (string) $collectionKey,
                    ),
                );
            }

            return $lines;
        }

        $products = $this->loadProductsForHybridSales($productsSold);
        /** @var array<string, int> $credits */
        $credits = [];

        foreach ($productsSold as $row) {
            $amount = (int) ($row['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $product = $this->resolveProductFromSoldRow($row, $products);
            $vendor = $product?->vendor;

            if ($vendor && (float) ($vendor->commission_percent ?? 0) > 0) {
                $this->accumulateVendorCommissionCredits($integration, $vendor, $amount, $credits);

                continue;
            }

            $salesAccount = $this->resolveHybridSalesAccount($integration, $product, $session);
            if (! $salesAccount) {
                continue;
            }
            $this->accumulateCredit(
                $credits,
                $salesAccount,
                $amount,
                $this->salesDescriptionForHybridProduct($product),
            );
        }

        $this->flushAggregatedCommissionCredits($integration, $credits);

        return $this->creditLinesFromAccumulatedMap($credits);
    }

    /**
     * Hybrid sales from Z-report {@see PosSessionsTable::calculateSalesByVendor()} — matches “Salg per leverandør”.
     *
     * @param  list<array<string, mixed>>  $vendorRows
     * @return list<array{account: string, debit_minor: int, credit_minor: int, description: string}>
     */
    protected function buildHybridSalesCreditLinesFromSalesByVendor(
        PosSession $session,
        PowerOfficeIntegration $integration,
        array $zReport,
        array $vendorRows,
    ): array {
        /** @var array<string, int> $credits */
        $credits = [];

        foreach ($vendorRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $amount = (int) ($row['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $commissionAmount = (int) ($row['commission_amount'] ?? 0);
            $vendorId = isset($row['id']) ? (string) $row['id'] : 'unknown';

            if ($vendorId === 'no-vendor' || $vendorId === 'unknown') {
                $this->accumulateHybridStoreCreditsByArticleGroup(
                    $session,
                    $integration,
                    $zReport,
                    $credits,
                    $amount,
                );

                continue;
            }

            if (! is_numeric($vendorId)) {
                continue;
            }

            $vendor = Vendor::query()->find((int) $vendorId);
            if (! $vendor instanceof Vendor) {
                $account = PowerOfficeLedgerSettings::defaultSalesAccount($integration);
                if ($account) {
                    $this->accumulateCredit(
                        $credits,
                        $account,
                        $amount,
                        PowerOfficeLedgerLineDescriptions::vendorNameFromRow($row['name'] ?? null),
                    );
                }

                continue;
            }

            if (! filled($vendor->supplier_ledger_account_number) && (float) ($vendor->commission_percent ?? 0) <= 0) {
                $this->accumulateHybridStoreCreditsByArticleGroup(
                    $session,
                    $integration,
                    $zReport,
                    $credits,
                    $amount,
                    $vendor,
                );

                continue;
            }

            $this->accumulateVendorCreditsFromZReportRow(
                $integration,
                $vendor,
                $amount,
                $commissionAmount,
                $credits,
            );
        }

        $this->flushAggregatedCommissionCredits($integration, $credits);

        return $this->creditLinesFromAccumulatedMap($credits);
    }

    /**
     * Store-owned turnover: split the Z-report store bucket across article groups / collections.
     *
     * @param  array<string, int>  $credits
     */
    protected function accumulateHybridStoreCreditsByArticleGroup(
        PosSession $session,
        PowerOfficeIntegration $integration,
        array $zReport,
        array &$credits,
        int $targetMinor,
        ?Vendor $limitToVendor = null,
    ): void {
        $productsSold = $this->productsSoldForHybridSales($session, $zReport);
        if (! is_array($productsSold) || $productsSold === []) {
            $account = $this->resolveDefaultStoreSalesAccount($integration, $session);
            if ($account && $targetMinor > 0) {
                $this->accumulateCredit(
                    $credits,
                    $account,
                    $targetMinor,
                    PowerOfficeLedgerLineDescriptions::categorySales(''),
                );
            }

            return;
        }

        $products = $this->loadProductsForHybridSales($productsSold);
        /** @var array<string, int> $storeCredits */
        $storeCredits = [];

        foreach ($productsSold as $row) {
            $amount = (int) ($row['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $product = $this->resolveProductFromSoldRow($row, $products);
            $productVendor = $product?->vendor;

            if ($limitToVendor !== null) {
                if ($productVendor?->getKey() !== $limitToVendor->getKey()) {
                    continue;
                }
            } elseif ($productVendor !== null && filled($productVendor->supplier_ledger_account_number)) {
                continue;
            } elseif ($productVendor !== null && (float) ($productVendor->commission_percent ?? 0) > 0) {
                continue;
            }

            $salesAccount = $this->resolveHybridSalesAccount($integration, $product, $session);
            if (! $salesAccount) {
                continue;
            }

            $this->accumulateCredit(
                $storeCredits,
                $salesAccount,
                $amount,
                $this->salesDescriptionForHybridProduct($product),
            );
        }

        if ($storeCredits === []) {
            $account = $this->resolveDefaultStoreSalesAccount($integration, $session);
            if ($account && $targetMinor > 0) {
                $this->accumulateCredit(
                    $credits,
                    $account,
                    $targetMinor,
                    PowerOfficeLedgerLineDescriptions::categorySales(''),
                );
            }

            return;
        }

        $scaled = $this->scaleCreditsMapToTarget($storeCredits, $targetMinor);

        foreach ($scaled as $key => $creditMinor) {
            if ($creditMinor <= 0) {
                continue;
            }
            [$account, $description] = explode("\0", $key, 2);
            $this->accumulateCredit($credits, $account, $creditMinor, $description);
        }
    }

    /**
     * @param  array<string, int>  $credits
     */
    protected function accumulateVendorCreditsFromZReportRow(
        PowerOfficeIntegration $integration,
        Vendor $vendor,
        int $grossMinor,
        int $commissionMinor,
        array &$credits,
    ): void {
        if ($commissionMinor <= 0 && (float) ($vendor->commission_percent ?? 0) > 0) {
            $commissionMinor = (int) round($grossMinor * ((float) $vendor->commission_percent / 100));
        }

        $supplierAccount = $this->resolveVendorSupplierAccount($integration, $vendor);

        if ($commissionMinor > 0) {
            if (! filled($supplierAccount)) {
                throw new MissingPowerOfficeMappingException(['vendor:'.$vendor->getKey()]);
            }

            $vendorShareMinor = $grossMinor - $commissionMinor;
            if ($vendorShareMinor > 0) {
                $this->accumulateCredit(
                    $credits,
                    $supplierAccount,
                    $vendorShareMinor,
                    PowerOfficeLedgerLineDescriptions::vendorName($vendor),
                );
            }

            $this->pendingAggregatedCommissionMinor += $commissionMinor;
            if ($this->pendingAggregatedCommissionAccount === null) {
                $this->pendingAggregatedCommissionAccount = $this->resolveVendorCommissionAccount($integration, $vendor);
            }

            return;
        }

        $account = $supplierAccount ?? PowerOfficeLedgerSettings::defaultSalesAccount($integration);
        if (filled($account)) {
            $this->accumulateCredit(
                $credits,
                $account,
                $grossMinor,
                PowerOfficeLedgerLineDescriptions::vendorName($vendor),
            );
        }
    }

    /**
     * @param  array<string, int>  $credits
     */
    protected function flushAggregatedCommissionCredits(
        PowerOfficeIntegration $integration,
        array &$credits,
    ): void {
        if ($this->pendingAggregatedCommissionMinor <= 0) {
            return;
        }

        $commissionAccount = $this->pendingAggregatedCommissionAccount
            ?? PowerOfficeLedgerSettings::commissionRevenueAccount($integration);
        if (! filled($commissionAccount)) {
            throw new MissingPowerOfficeMappingException([], 'Commission revenue account (3023) is not configured.');
        }

        $this->accumulateCredit(
            $credits,
            (string) $commissionAccount,
            $this->pendingAggregatedCommissionMinor,
            PowerOfficeLedgerLineDescriptions::aggregatedStuttreistCommission(),
        );
        $this->pendingAggregatedCommissionMinor = 0;
        $this->pendingAggregatedCommissionAccount = null;
    }

    /**
     * @return list<array{account: string, debit_minor: int, credit_minor: int, description: string}>
     */
    protected function buildVendorSalesCreditLines(
        PosSession $session,
        PowerOfficeIntegration $integration,
        array $zReport,
    ): array {
        $rows = $zReport['sales_by_vendor'] ?? [];
        if ($rows instanceof Collection) {
            $rows = $rows->all();
        }
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        /** @var array<string, int> $credits */
        $credits = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $amount = (int) ($row['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $vendorId = isset($row['id']) ? (string) $row['id'] : 'unknown';
            if ($vendorId === 'no-vendor' || $vendorId === 'unknown') {
                $account = PowerOfficeLedgerSettings::defaultSalesAccount($integration);
                if ($account) {
                    $this->accumulateCredit(
                        $credits,
                        $account,
                        $amount,
                        PowerOfficeLedgerLineDescriptions::categorySales(''),
                    );
                }

                continue;
            }

            $vendor = Vendor::query()->find((int) $vendorId);
            if (! $vendor) {
                $account = PowerOfficeLedgerSettings::defaultSalesAccount($integration);
                if ($account) {
                    $this->accumulateCredit(
                        $credits,
                        $account,
                        $amount,
                        PowerOfficeLedgerLineDescriptions::vendorNameFromRow($row['name'] ?? null),
                    );
                }

                continue;
            }

            if ((float) ($vendor->commission_percent ?? 0) > 0) {
                $this->accumulateVendorCreditsFromZReportRow(
                    $integration,
                    $vendor,
                    $amount,
                    (int) ($row['commission_amount'] ?? 0),
                    $credits,
                );

                continue;
            }

            $account = $this->resolveVendorSupplierAccount($integration, $vendor)
                ?? PowerOfficeLedgerSettings::defaultSalesAccount($integration);
            if ($account) {
                $this->accumulateCredit(
                    $credits,
                    $account,
                    $amount,
                    PowerOfficeLedgerLineDescriptions::vendorName($vendor),
                );
            }
        }

        $this->flushAggregatedCommissionCredits($integration, $credits);

        return $this->creditLinesFromAccumulatedMap($credits);
    }

    /**
     * @param  array<string, int>  $credits
     */
    protected function accumulateVendorCommissionCredits(
        PowerOfficeIntegration $integration,
        Vendor $vendor,
        int $grossMinor,
        array &$credits,
    ): void {
        $commissionPercent = (float) $vendor->commission_percent;
        $commissionMinor = (int) round($grossMinor * ($commissionPercent / 100));
        $supplierAccount = $this->resolveVendorSupplierAccount($integration, $vendor);

        if ($commissionMinor > 0) {
            if (! filled($supplierAccount)) {
                throw new MissingPowerOfficeMappingException(['vendor:'.$vendor->getKey()]);
            }

            $vendorShareMinor = $grossMinor - $commissionMinor;
            if ($vendorShareMinor > 0) {
                $this->accumulateCredit(
                    $credits,
                    $supplierAccount,
                    $vendorShareMinor,
                    PowerOfficeLedgerLineDescriptions::vendorName($vendor),
                );
            }

            $this->pendingAggregatedCommissionMinor += $commissionMinor;
            if ($this->pendingAggregatedCommissionAccount === null) {
                $this->pendingAggregatedCommissionAccount = $this->resolveVendorCommissionAccount($integration, $vendor);
            }

            return;
        }

        $fallback = $supplierAccount ?? PowerOfficeLedgerSettings::defaultSalesAccount($integration);
        if (filled($fallback)) {
            $this->accumulateCredit(
                $credits,
                $fallback,
                $grossMinor,
                PowerOfficeLedgerLineDescriptions::vendorName($vendor),
            );
        }
    }

    protected function resolveDefaultStoreSalesAccount(PowerOfficeIntegration $integration, ?PosSession $session = null): ?string
    {
        $fromSettings = PowerOfficeLedgerSettings::defaultSalesAccount($integration);
        if (filled($fromSettings)) {
            return $fromSettings;
        }

        $fromMapping = $integration->accountMappings()
            ->where('is_active', true)
            ->whereNotNull('sales_account_no')
            ->orderBy('id')
            ->value('sales_account_no');

        if (filled($fromMapping)) {
            return (string) $fromMapping;
        }

        return $this->resolveSalesAccountNo($integration, PowerOfficeMappingBasis::Category, '0', $session)
            ?? $this->resolveSalesAccountNo($integration, PowerOfficeMappingBasis::Vat, '25', $session);
    }

    protected function resolveVendorSupplierAccount(PowerOfficeIntegration $integration, Vendor $vendor): ?string
    {
        if (filled($vendor->supplier_ledger_account_number)) {
            return (string) $vendor->supplier_ledger_account_number;
        }

        return PowerOfficeLedgerSettings::defaultSalesAccount($integration);
    }

    protected function resolveVendorCommissionAccount(PowerOfficeIntegration $integration, Vendor $vendor): ?string
    {
        if (filled($vendor->commission_revenue_account_number)) {
            return (string) $vendor->commission_revenue_account_number;
        }

        return PowerOfficeLedgerSettings::commissionRevenueAccount($integration);
    }

    /**
     * @param  array<string, int>  $credits
     * @return array<string, int>
     */
    protected function scaleCreditsMapToTarget(array $credits, int $targetMinor): array
    {
        if ($targetMinor <= 0) {
            return $credits;
        }

        $positive = array_filter($credits, fn (int $amount): bool => $amount > 0);
        $sum = array_sum($positive);
        if ($sum <= 0 || $sum === $targetMinor) {
            return $credits;
        }

        $scaled = [];
        $allocated = 0;
        $accounts = array_keys($positive);
        $lastIndex = count($accounts) - 1;

        foreach ($accounts as $index => $account) {
            if ($index === $lastIndex) {
                $scaled[$account] = max(0, $targetMinor - $allocated);

                continue;
            }

            $share = (int) round($positive[$account] * $targetMinor / $sum);
            $scaled[$account] = $share;
            $allocated += $share;
        }

        return $scaled;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function productsSoldForHybridSales(PosSession $session, array $zReport): array
    {
        // Accounting scope: session receipts + charge metadata only (matches net Z-report turnover).
        $calculated = PosSessionsTable::calculateProductsSold($session, accountingScope: true)->values()->all();
        if ($calculated !== []) {
            return $calculated;
        }

        $productsSold = $zReport['products_sold'] ?? [];
        if ($productsSold instanceof Collection) {
            $productsSold = $productsSold->all();
        }

        return is_array($productsSold) ? $productsSold : [];
    }

    /**
     * @param  list<array<string, mixed>>  $productsSold
     * @return \Illuminate\Support\Collection<int, ConnectedProduct>
     */
    protected function loadProductsForHybridSales(array $productsSold): Collection
    {
        $productIds = [];
        foreach ($productsSold as $row) {
            if (! empty($row['product_id'])) {
                $productIds[] = (int) $row['product_id'];
            }
        }
        $productIds = array_values(array_unique($productIds));

        return ConnectedProduct::query()
            ->whereIn('id', $productIds)
            ->with([
                'vendor',
                'articleGroupCode',
                'collections' => fn ($q) => $q->orderBy('collection_product.sort_order'),
            ])
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<int, ConnectedProduct>  $products
     */
    protected function resolveProductFromSoldRow(array $row, Collection $products): ?ConnectedProduct
    {
        $pid = isset($row['product_id']) ? (int) $row['product_id'] : null;
        if (! $pid || ! $products->has($pid)) {
            return null;
        }

        return $products[$pid];
    }

    protected function collectionKeyForProduct(?ConnectedProduct $product): string
    {
        if ($product === null) {
            return '0';
        }

        $firstCollection = $product->collections->first();

        return $firstCollection ? (string) $firstCollection->getKey() : '0';
    }

    protected function articleGroupKeyForProduct(?ConnectedProduct $product): string
    {
        if ($product === null || ! filled($product->article_group_code)) {
            return '';
        }

        return (string) $product->article_group_code;
    }

    protected function resolveHybridSalesAccount(
        PowerOfficeIntegration $integration,
        ?ConnectedProduct $product,
        ?PosSession $session = null,
    ): ?string {
        $articleGroupKey = $this->articleGroupKeyForProduct($product);
        if ($articleGroupKey !== '') {
            $articleAccount = $this->resolveSalesAccountNo(
                $integration,
                PowerOfficeMappingBasis::ArticleGroup,
                $articleGroupKey,
                $session,
            );
            if (filled($articleAccount)) {
                return $articleAccount;
            }
        }

        $collectionKey = $this->collectionKeyForProduct($product);

        return $this->resolveSalesAccountNo(
            $integration,
            PowerOfficeMappingBasis::Category,
            $collectionKey,
            $session,
        );
    }

    /**
     * @return list<array{account: string, debit_minor: int, credit_minor: int, description: string}>
     */
    protected function buildVippsFeeLines(PosSession $session, PowerOfficeIntegration $integration): array
    {
        $feeAccount = PowerOfficeLedgerSettings::paymentMethodFeeDebitAccount($integration, 'vipps');
        if (! $feeAccount) {
            return [];
        }

        $fees = $this->vippsFeesMinorForSession($session);
        if ($fees <= 0) {
            return [];
        }

        return [[
            'account' => $feeAccount,
            'debit_minor' => $fees,
            'credit_minor' => 0,
            'description' => PowerOfficeLedgerLineDescriptions::vippsFees(),
        ]];
    }

    protected function vippsFeesMinorForSession(PosSession $session): int
    {
        return (int) ConnectedCharge::query()
            ->where('pos_session_id', $session->id)
            ->whereIn('status', ['succeeded', 'refunded'])
            ->where(function ($query): void {
                $query->where('payment_method', 'vipps')
                    ->orWhere('payment_method', 'ilike', '%vipps%');
            })
            ->sum('application_fee_amount');
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
                'description' => PowerOfficeLedgerLineDescriptions::giftCardLiability(),
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
                    'description' => PowerOfficeLedgerLineDescriptions::paymentFeesSettlement(),
                ];
                $extra[] = [
                    'account' => $fee['debit'],
                    'debit_minor' => $fees,
                    'credit_minor' => 0,
                    'description' => PowerOfficeLedgerLineDescriptions::paymentFeesExpense(),
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
                    'description' => PowerOfficeLedgerLineDescriptions::payoutSettlement(),
                ];
                $extra[] = [
                    'account' => $po['debit'],
                    'debit_minor' => $payout,
                    'credit_minor' => 0,
                    'description' => PowerOfficeLedgerLineDescriptions::payoutBank(),
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
        $normalized = PowerOfficeStandardVatRates::normalizeSalesNetMinorByVatRateMap($zReport['sales_net_minor_by_vat_rate'] ?? null);
        if ($normalized !== []) {
            return $normalized;
        }

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
        PosSession $session,
        array $zReport,
        PowerOfficeIntegration $integration,
        PowerOfficeMappingBasis $basis,
        PowerOfficeAccountMapping $fallback,
        array $buckets,
    ): array {
        $lines = [];
        $vippsFeeAccount = PowerOfficeLedgerSettings::paymentMethodFeeDebitAccount($integration, 'vipps');
        $vippsFees = $vippsFeeAccount ? $this->vippsFeesMinorForSession($session) : 0;
        $remainingVippsFees = $vippsFees;

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
                if ($remainingVippsFees > 0 && $this->isVippsPaymentMethod($methodKey)) {
                    $feeReduction = min($amount, $remainingVippsFees);
                    $amount -= $feeReduction;
                    $remainingVippsFees -= $feeReduction;
                }
                if ($amount <= 0) {
                    continue;
                }
                $account = $this->resolvePaymentDebitAccount($integration, $basis, $fallback, $methodKey);
                if (! $account) {
                    continue;
                }
                $lines[] = [
                    'account' => $account,
                    'debit_minor' => $amount,
                    'credit_minor' => 0,
                    'description' => PowerOfficeLedgerLineDescriptions::payment((string) $method, $session),
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
                        'description' => PowerOfficeLedgerLineDescriptions::payment((string) $method, $session),
                    ];
                }
            }

            return $lines;
        }

        $netCash = (int) ($zReport['net_cash_amount'] ?? 0);
        $netCard = (int) ($zReport['net_card_amount'] ?? 0);
        $netMobile = (int) ($zReport['net_mobile_amount'] ?? 0);
        $netOther = (int) ($zReport['net_other_amount'] ?? 0);

        $cardAmount = $netCard + $netMobile + $netOther;
        if ($vippsFees > 0 && $cardAmount > 0) {
            $cardAmount = max(0, $cardAmount - $vippsFees);
        }

        $map = [
            'cash' => $netCash,
            'card' => $cardAmount,
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
                'description' => PowerOfficeLedgerLineDescriptions::payment($method, $session),
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
        ?PosSession $session = null,
    ): ?string {
        if ($basis === PowerOfficeMappingBasis::Vendor && is_numeric($basisKey)) {
            $vendor = Vendor::query()->find((int) $basisKey);
            if ($vendor instanceof Vendor) {
                return $this->resolveVendorSupplierAccount($integration, $vendor);
            }
        }

        $row = $this->findMapping($integration, $basis, $basisKey);
        if ($row && filled($row->sales_account_no)) {
            return (string) $row->sales_account_no;
        }

        if ($basis === PowerOfficeMappingBasis::Category || $basis === PowerOfficeMappingBasis::Vendor) {
            return PowerOfficeLedgerSettings::defaultSalesAccount($integration);
        }

        return null;
    }

    protected function isVippsPaymentMethod(string $method): bool
    {
        $m = strtolower(trim($method));

        return $m === 'vipps' || str_contains($m, 'vipps');
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
