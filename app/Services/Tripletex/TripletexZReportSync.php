<?php

namespace App\Services\Tripletex;

use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\PosSession;
use Illuminate\Support\Carbon;

/**
 * Sync POSitiv Z-report data to Tripletex as ledger vouchers.
 *
 * Logic mirrors Merano-Tripletex-Sync (Zettle + Tripletex): build voucher lines
 * from sales by payment type (card/Stripe, mobile/Vipps, cash, other), then
 * one voucher per session date. Amounts from Z-report are in øre; we convert
 * to NOK for Tripletex.
 *
 * @see /Users/waparty/Desktop/private/Merano-Tripletex-Sync (main.js, voucherBuilder.js)
 */
class TripletexZReportSync
{
    public function __construct(
        protected TripletexApiClient $client
    ) {}

    /**
     * Sync a closed POS session's Z-report to Tripletex.
     * Uses session's closing_data['z_report_data'] or generates it.
     * Stores tripletex_voucher_id in closing_data on success.
     *
     * @return array{success: bool, voucher_id?: int, error?: string}
     */
    public function syncSession(PosSession $session): array
    {
        if ($session->status !== 'closed') {
            return ['success' => false, 'error' => 'Session must be closed to sync Z-report.'];
        }

        $closingData = $session->closing_data ?? [];
        if (isset($closingData['tripletex_voucher_id'])) {
            return ['success' => true, 'voucher_id' => $closingData['tripletex_voucher_id']];
        }

        $report = $closingData['z_report_data'] ?? PosSessionsTable::generateZReport($session);
        $voucherLines = $this->buildVoucherLinesFromZReport($report, $session);
        if (empty($voucherLines)) {
            return ['success' => false, 'error' => 'No voucher lines to post (zero sales?).'];
        }

        $expiresAt = Carbon::now()->addDays(2);
        try {
            $sessionToken = $this->client->getSessionToken($expiresAt);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Tripletex session token failed: '.$e->getMessage()];
        }

        $accountNumbers = array_unique(array_column($voucherLines, 'accountNumber'));
        $accountMap = [];
        foreach ($accountNumbers as $num) {
            $acct = $this->client->getAccountByNumber($sessionToken, (int) $num);
            if (! $acct) {
                return ['success' => false, 'error' => "Tripletex account {$num} not found."];
            }
            $accountMap[$num] = $acct;
        }

        $voucherData = $this->buildTripletexVoucherPayload($report, $voucherLines, $accountMap, $session);
        try {
            $result = $this->client->createVoucher($sessionToken, $voucherData);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Tripletex create voucher failed: '.$e->getMessage()];
        }

        $voucherId = $result['value']['id'] ?? null;
        if ($voucherId) {
            $closingData['tripletex_voucher_id'] = $voucherId;
            $closingData['tripletex_synced_at'] = now()->toISOString();
            $session->closing_data = $closingData;
            $session->saveQuietly();
        }

        return ['success' => true, 'voucher_id' => $voucherId];
    }

    /**
     * Build flat voucher lines from Z-report (amounts in øre → we output NOK in lines).
     * One pair per payment type: debit clearing, credit sales (or vice versa per convention).
     */
    protected function buildVoucherLinesFromZReport(array $report, PosSession $session): array
    {
        $lines = [];
        $date = $session->closed_at ? $session->closed_at->format('Y-m-d') : now()->format('Y-m-d');
        $ref = "POS session {$session->session_number}";

        $accounts = config('tripletex.accounts', []);
        $salesAccount = $accounts['sales'] ?? 3000;
        $clearingStripe = $accounts['clearing_stripe'] ?? 1901;
        $clearingVipps = $accounts['clearing_vipps'] ?? 1902;
        $clearingCash = $accounts['clearing_cash'] ?? 1900;
        $clearingOther = $accounts['clearing_other'] ?? 1901;
        $vatType = config('tripletex.vat_type_id', 31);

        // Net amounts from report (in øre). X/Z-report already has net_* = sales - refunds per payment type.
        $netCash = (int) ($report['net_cash_amount'] ?? ($report['cash_amount'] ?? 0) - ($report['cash_refunded'] ?? 0));
        $netCard = (int) ($report['net_card_amount'] ?? ($report['card_amount'] ?? 0) - ($report['card_refunded'] ?? 0));
        $netMobile = (int) ($report['net_mobile_amount'] ?? ($report['mobile_amount'] ?? 0) - ($report['mobile_refunded'] ?? 0));
        $netOther = (int) ($report['net_other_amount'] ?? ($report['other_amount'] ?? 0) - ($report['other_refunded'] ?? 0));

        $toNok = fn ($ore) => round((int) $ore / 100, 2);

        if ($netCash > 0) {
            $amount = $toNok($netCash);
            $lines[] = ['description' => "Salg kontant, {$date}, {$ref}", 'accountNumber' => $salesAccount, 'amount' => $amount, 'credit' => true, 'transactionDate' => $date, 'vatType' => $vatType];
            $lines[] = ['description' => "Kontant inntekt, {$date}, {$ref}", 'accountNumber' => $clearingCash, 'amount' => $amount, 'credit' => false, 'transactionDate' => $date];
        }
        if ($netCard > 0) {
            $amount = $toNok($netCard);
            $lines[] = ['description' => "Salg kort (Stripe), {$date}, {$ref}", 'accountNumber' => $salesAccount, 'amount' => $amount, 'credit' => true, 'transactionDate' => $date, 'vatType' => $vatType];
            $lines[] = ['description' => "Stripe inntekt, {$date}, {$ref}", 'accountNumber' => $clearingStripe, 'amount' => $amount, 'credit' => false, 'transactionDate' => $date];
        }
        if ($netMobile > 0) {
            $amount = $toNok($netMobile);
            $lines[] = ['description' => "Salg mobil (Vipps etc.), {$date}, {$ref}", 'accountNumber' => $salesAccount, 'amount' => $amount, 'credit' => true, 'transactionDate' => $date, 'vatType' => $vatType];
            $lines[] = ['description' => "Vipps inntekt, {$date}, {$ref}", 'accountNumber' => $clearingVipps, 'amount' => $amount, 'credit' => false, 'transactionDate' => $date];
        }
        if ($netOther > 0) {
            $amount = $toNok($netOther);
            $lines[] = ['description' => "Salg annen betaling, {$date}, {$ref}", 'accountNumber' => $salesAccount, 'amount' => $amount, 'credit' => true, 'transactionDate' => $date, 'vatType' => $vatType];
            $lines[] = ['description' => "Annen inntekt, {$date}, {$ref}", 'accountNumber' => $clearingOther, 'amount' => $amount, 'credit' => false, 'transactionDate' => $date];
        }

        return $lines;
    }

    /**
     * Build Tripletex voucher payload (same shape as Merano voucherBuilder.js).
     *
     * @param  array  $voucherLines  [{ accountNumber, amount, credit, transactionDate, description, vatType? }]
     * @param  array  $accountMap  account number => Tripletex account object
     */
    protected function buildTripletexVoucherPayload(array $report, array $voucherLines, array $accountMap, PosSession $session): array
    {
        $dates = array_unique(array_column($voucherLines, 'transactionDate'));
        sort($dates);
        $headerDate = $dates[0] ?? $session->closed_at?->format('Y-m-d') ?? now()->format('Y-m-d');
        $ref = "POS session {$session->session_number}";
        $description = "POSitiv Z-rapport {$headerDate}, {$ref}";

        $postings = [];
        foreach ($voucherLines as $idx => $l) {
            $account = $accountMap[$l['accountNumber']] ?? null;
            if (! $account) {
                throw new \InvalidArgumentException("No Tripletex account for number {$l['accountNumber']}");
            }
            $amt = round((float) $l['amount'], 2);
            $signed = $l['credit'] ? -$amt : $amt;
            $posting = [
                'row' => $idx + 1,
                'description' => $l['description'],
                'account' => $account,
                'amountGross' => $signed,
                'amountGrossCurrency' => $signed,
                'date' => $l['transactionDate'] ?? $headerDate,
            ];
            if (! empty($l['vatType'])) {
                $posting['vatType'] = ['id' => $l['vatType']];
            }
            $postings[] = $posting;
        }

        return [
            'date' => $headerDate,
            'description' => $description,
            'voucherType' => ['id' => 0],
            'postings' => $postings,
        ];
    }
}
