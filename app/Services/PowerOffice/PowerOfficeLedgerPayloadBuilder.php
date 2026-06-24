<?php

namespace App\Services\PowerOffice;

use App\Enums\PowerOfficeMappingBasis;
use App\Exceptions\PowerOffice\MissingPowerOfficeMappingException;
use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PosSession;
use App\Models\PowerOfficeAccountMapping;
use App\Models\PowerOfficeIntegration;
use App\Models\Vendor;
use App\Support\PowerOffice\PowerOfficeLedgerSettings;
use App\Support\PowerOffice\PowerOfficeStandardVatRates;
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
        $vatAmount = (int) ($zReport['vat_amount'] ?? 0);
        $tipsAmount = (int) ($zReport['total_tips'] ?? 0);
        $applyDepartment = PowerOfficeLedgerSettings::departmentNo($integration) !== null;

        if ($basis === PowerOfficeMappingBasis::Category) {
            $lines = array_merge($lines, $this->buildHybridCollectionSalesCreditLines($session, $integration, $zReport, $applyDepartment));
        } elseif ($basis === PowerOfficeMappingBasis::Vendor) {
            $lines = array_merge($lines, $this->buildVendorSalesCreditLines($session, $integration, $zReport, $applyDepartment));
        } else {
            $salesBuckets = PowerOfficeStandardVatRates::resolveSalesCreditBucketsForLedger($basis, $zReport, $mappingBuckets);

            foreach ($salesBuckets as $basisKey => $amount) {
                if ($amount <= 0) {
                    continue;
                }
                $salesAccount = $this->resolveSalesAccountForCreditLine(
                    $integration,
                    $basis,
                    $mappingBuckets,
                    $zReport,
                    (string) $basisKey,
                    $session,
                );
                if (! $salesAccount) {
                    continue;
                }
                $lines[] = $this->salesCreditLine(
                    $session,
                    $salesAccount,
                    $amount,
                    'sales ('.$basisKey.')',
                    $applyDepartment,
                );
            }
        }

        if ($vatAmount > 0 && $defaultMapping->vat_account_no) {
            $vatByRate = PowerOfficeStandardVatRates::normalizeVatMinorByVatRateMap($zReport['vat_minor_by_vat_rate'] ?? null);
            if ($vatByRate !== []) {
                foreach ($vatByRate as $rateKey => $vatPart) {
                    $vatPart = (int) $vatPart;
                    if ($vatPart <= 0) {
                        continue;
                    }
                    $lines[] = [
                        'account' => $defaultMapping->vat_account_no,
                        'debit_minor' => 0,
                        'credit_minor' => $vatPart,
                        'description' => 'Z-report '.$session->session_number.' VAT '.$rateKey.'%',
                        'apply_department' => $applyDepartment,
                    ];
                }
            } else {
                $lines[] = [
                    'account' => $defaultMapping->vat_account_no,
                    'debit_minor' => 0,
                    'credit_minor' => $vatAmount,
                    'description' => 'Z-report '.$session->session_number.' VAT',
                    'apply_department' => $applyDepartment,
                ];
            }
        }

        if ($tipsAmount > 0 && $defaultMapping->tips_account_no) {
            $lines[] = [
                'account' => $defaultMapping->tips_account_no,
                'debit_minor' => 0,
                'credit_minor' => $tipsAmount,
                'description' => 'Z-report '.$session->session_number.' tips',
                'apply_department' => $applyDepartment,
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
            'department_no' => PowerOfficeLedgerSettings::departmentNo($integration),
            'lines' => $lines,
        ];
    }

    /**
     * @return list<array{account: string, debit_minor: int, credit_minor: int, description: string, apply_department?: bool}>
     */
    protected function salesCreditLine(
        PosSession $session,
        string $account,
        int $creditMinor,
        string $label,
        bool $applyDepartment,
    ): array {
        $line = [
            'account' => $account,
            'debit_minor' => 0,
            'credit_minor' => $creditMinor,
            'description' => 'Z-report '.$session->session_number.' '.$label,
        ];
        if ($applyDepartment) {
            $line['apply_department'] = true;
        }

        return $line;
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
     * @return list<array{account: string, debit_minor: int, credit_minor: int, description: string, apply_department?: bool}>
     */
    protected function buildHybridCollectionSalesCreditLines(
        PosSession $session,
        PowerOfficeIntegration $integration,
        array $zReport,
        bool $applyDepartment,
    ): array {
        $productsSold = $this->productsSoldForHybridSales($session, $zReport);
        if (! is_array($productsSold) || $productsSold === []) {
            $lines = [];
            foreach ($this->bucketsForCollection($session, $zReport) as $collectionKey => $amount) {
                if ($amount <= 0) {
                    continue;
                }
                $account = $this->resolveSalesAccountNo($integration, PowerOfficeMappingBasis::Category, (string) $collectionKey, $session);
                if (! $account) {
                    continue;
                }
                $lines[] = $this->salesCreditLine($session, $account, $amount, 'sales ('.$collectionKey.')', $applyDepartment);
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
            $credits[$salesAccount] = ($credits[$salesAccount] ?? 0) + $amount;
        }

        $lines = [];
        foreach ($credits as $account => $creditMinor) {
            if ($creditMinor <= 0) {
                continue;
            }
            $lines[] = $this->salesCreditLine($session, $account, $creditMinor, 'sales', $applyDepartment);
        }

        return $lines;
    }

    /**
     * @return list<array{account: string, debit_minor: int, credit_minor: int, description: string, apply_department?: bool}>
     */
    protected function buildVendorSalesCreditLines(
        PosSession $session,
        PowerOfficeIntegration $integration,
        array $zReport,
        bool $applyDepartment,
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
                    $credits[$account] = ($credits[$account] ?? 0) + $amount;
                }

                continue;
            }

            $vendor = Vendor::query()->find((int) $vendorId);
            if (! $vendor) {
                $account = PowerOfficeLedgerSettings::defaultSalesAccount($integration);
                if ($account) {
                    $credits[$account] = ($credits[$account] ?? 0) + $amount;
                }

                continue;
            }

            if ((float) ($vendor->commission_percent ?? 0) > 0) {
                $this->accumulateVendorCommissionCredits($integration, $vendor, $amount, $credits);

                continue;
            }

            $account = $this->resolveVendorSupplierAccount($integration, $vendor)
                ?? PowerOfficeLedgerSettings::defaultSalesAccount($integration);
            if ($account) {
                $credits[$account] = ($credits[$account] ?? 0) + $amount;
            }
        }

        $lines = [];
        foreach ($credits as $account => $creditMinor) {
            if ($creditMinor <= 0) {
                continue;
            }
            $lines[] = $this->salesCreditLine($session, $account, $creditMinor, 'sales vendor', $applyDepartment);
        }

        return $lines;
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
        $vendorShareMinor = $grossMinor - $commissionMinor;
        $supplierAccount = $this->resolveVendorSupplierAccount($integration, $vendor);
        $commissionAccount = $this->resolveVendorCommissionAccount($integration, $vendor);

        if ($commissionMinor > 0 && filled($commissionAccount)) {
            if ($vendorShareMinor > 0 && ! filled($supplierAccount)) {
                throw new MissingPowerOfficeMappingException(['vendor:'.$vendor->getKey()]);
            }

            if ($vendorShareMinor > 0 && filled($supplierAccount)) {
                $credits[$supplierAccount] = ($credits[$supplierAccount] ?? 0) + $vendorShareMinor;
            }
            $credits[$commissionAccount] = ($credits[$commissionAccount] ?? 0) + $commissionMinor;

            return;
        }

        $fallback = $supplierAccount ?? PowerOfficeLedgerSettings::defaultSalesAccount($integration);
        if (filled($fallback)) {
            $credits[$fallback] = ($credits[$fallback] ?? 0) + $grossMinor;
        }
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
     * @return list<array<string, mixed>>
     */
    protected function productsSoldForHybridSales(PosSession $session, array $zReport): array
    {
        $productsSold = $zReport['products_sold'] ?? [];
        if ($productsSold instanceof Collection) {
            $productsSold = $productsSold->all();
        }
        if (is_array($productsSold) && $productsSold !== []) {
            return $productsSold;
        }

        return PosSessionsTable::calculateProductsSold($session)->values()->all();
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
            'description' => 'Z-report '.$session->session_number.' Vipps fees',
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

    protected function dominantPaymentMethodKeyForSalesAccount(array $zReport, array $mappingBuckets): ?string
    {
        $byNet = $zReport['by_payment_method_net'] ?? [];
        if ($byNet instanceof Collection) {
            $byNet = $byNet->all();
        }
        $bestKey = null;
        $bestAmt = -1;
        if (is_array($byNet)) {
            foreach ($byNet as $method => $data) {
                if (! is_array($data)) {
                    continue;
                }
                $amount = (int) ($data['amount'] ?? 0);
                if ($amount > $bestAmt) {
                    $bestAmt = $amount;
                    $bestKey = is_string($method) ? $method : (string) $method;
                }
            }
        }
        if ($bestKey !== null && $bestAmt > 0) {
            return $bestKey;
        }
        foreach ($mappingBuckets as $k => $amount) {
            $n = (int) $amount;
            if ($n > $bestAmt) {
                $bestAmt = $n;
                $bestKey = (string) $k;
            }
        }

        return ($bestKey !== null && $bestAmt > 0) ? $bestKey : null;
    }

    protected function resolveSalesAccountForCreditLine(
        PowerOfficeIntegration $integration,
        PowerOfficeMappingBasis $basis,
        array $mappingBuckets,
        array $zReport,
        string $salesBucketKey,
        ?PosSession $session = null,
    ): ?string {
        $vatSplit = PowerOfficeStandardVatRates::normalizeSalesNetMinorByVatRateMap($zReport['sales_net_minor_by_vat_rate'] ?? null);
        if ($basis === PowerOfficeMappingBasis::Vat || $vatSplit === [] || ! array_key_exists($salesBucketKey, $vatSplit)) {
            return $this->resolveSalesAccountNo($integration, $basis, $salesBucketKey, $session);
        }

        if ($basis === PowerOfficeMappingBasis::PaymentMethod) {
            $dominant = $this->dominantPaymentMethodKeyForSalesAccount($zReport, $mappingBuckets);
            if ($dominant !== null) {
                $acct = $this->resolveSalesAccountNo($integration, $basis, $dominant, $session);
                if (filled($acct)) {
                    return $acct;
                }
            }
        }

        $fallbackAccount = PowerOfficeLedgerSettings::defaultSalesAccount($integration);
        if (filled($fallbackAccount)) {
            return $fallbackAccount;
        }

        $firstKey = array_key_first($mappingBuckets);
        if (is_string($firstKey) && $firstKey !== '') {
            return $this->resolveSalesAccountNo($integration, $basis, $firstKey, $session);
        }

        return null;
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
