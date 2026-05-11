<?php

namespace App\Services\Tripletex;

use App\Enums\TripletexSyncRunStatus;
use App\Enums\TripletexSyncType;
use App\Models\ConnectedCharge;
use App\Models\Store;
use App\Models\StoreStripeBalanceTransaction;
use App\Models\StoreStripePayout;
use App\Models\TripletexIntegration;
use App\Models\TripletexSyncRun;
use App\Support\Tripletex\TripletexExternalTicketSalesMatch;
use App\Support\Tripletex\TripletexLedgerSettings;

final class TripletexPayoutReconciliationService
{
    public const int MINOR_TOLERANCE = 1;

    /**
     * Read-only comparison of Stripe mirror data vs the last successful Tripletex payout voucher payload.
     *
     * @return array<string, mixed>
     */
    public function reconcile(StoreStripePayout $payout, ?TripletexIntegration $integration = null): array
    {
        $integration ??= $payout->store?->tripletexIntegration;
        if (! $integration instanceof TripletexIntegration) {
            return $this->result('warn', ['Tripletex integration is not configured for this store.'], []);
        }

        $run = TripletexSyncRun::query()
            ->where('tripletex_integration_id', $integration->id)
            ->where('store_stripe_payout_id', $payout->id)
            ->where('sync_type', TripletexSyncType::Payout)
            ->where('status', TripletexSyncRunStatus::Success)
            ->latest('id')
            ->first();

        if (! $run || ! is_array($run->request_payload)) {
            return $this->result('warn', ['No successful Tripletex payout sync with stored request payload was found.'], []);
        }

        $payload = $run->request_payload;
        $lines = $payload['lines'] ?? [];
        if (! is_array($lines)) {
            $lines = [];
        }

        $store = $payout->store;
        if (! $store instanceof Store) {
            return $this->result('warn', ['Store relation missing on payout.'], []);
        }

        $classified = $this->classifyPayloadLines($lines);

        $payoutAmount = (int) $payout->amount;
        $mirrorFees = (int) StoreStripeBalanceTransaction::query()
            ->where('store_id', $store->getKey())
            ->where('stripe_payout_id', $payout->stripe_payout_id)
            ->sum('fee');

        $mirrorExternal = $this->mirrorExternalTicketTotalMinor($store, $integration, $payout);

        $messages = [];
        $deltas = [];

        $bankDebit = $classified['payout_bank_debit'];
        $dBank = abs($bankDebit - $payoutAmount);
        $deltas['payout_bank_vs_payout_amount'] = $bankDebit - $payoutAmount;
        if ($dBank > self::MINOR_TOLERANCE) {
            $messages[] = "Payout bank debit ({$bankDebit}) differs from Stripe payout amount ({$payoutAmount}) by {$dBank} minor.";
        }

        $feeDebits = $classified['application_fee_expense_debit'] + $classified['stripe_processing_fee_expense_debit'];
        $dFees = abs($feeDebits - $mirrorFees);
        $deltas['fee_debits_vs_mirror_fees'] = $feeDebits - $mirrorFees;
        if ($dFees > self::MINOR_TOLERANCE) {
            $messages[] = "Tripletex fee expense debits ({$feeDebits}) differ from mirror fee sum ({$mirrorFees}) by {$dFees} minor.";
        }

        if (TripletexLedgerSettings::externalTicketSalesEnabled($integration)) {
            $txExternal = $classified['external_ticket_sales_credit'];
            $dExt = abs($txExternal - $mirrorExternal);
            $deltas['external_ticket_sales_vs_mirror'] = $txExternal - $mirrorExternal;
            if ($dExt > self::MINOR_TOLERANCE) {
                $messages[] = "External ticket sales credits in Tripletex ({$txExternal}) differ from mirror detection ({$mirrorExternal}) by {$dExt} minor.";
            }
        }

        $balanced = ((int) ($classified['debit_total'] ?? 0) === (int) ($classified['credit_total'] ?? 0));
        if (! $balanced) {
            $messages[] = 'Tripletex payload lines are not balanced (debit total ≠ credit total).';
        }

        if (($classified['legacy_without_line_kind'] ?? false) === true) {
            $messages[] = 'Stored payload has no line_kind on lines; amounts may be incomplete — re-sync payout to Tripletex for full reconciliation.';
        }

        $status = $messages === [] && $balanced ? 'ok' : ($balanced ? 'warn' : 'fail');

        return $this->result($status, $messages, [
            'payout_amount_minor' => $payoutAmount,
            'mirror_fee_total_minor' => $mirrorFees,
            'mirror_external_ticket_total_minor' => $mirrorExternal,
            'tripletex_sync_run_id' => $run->id,
            'classified' => $classified,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    protected function classifyPayloadLines(array $lines): array
    {
        $debitTotal = 0;
        $creditTotal = 0;
        $payoutBankDebit = 0;
        $applicationFeeExpense = 0;
        $stripeFeeExpense = 0;
        $externalSalesCredit = 0;
        $legacy = true;

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $debitTotal += (int) ($line['debit_minor'] ?? 0);
            $creditTotal += (int) ($line['credit_minor'] ?? 0);
            $kind = (string) ($line['line_kind'] ?? '');
            if ($kind !== '') {
                $legacy = false;
            }
            if ($kind === 'payout_bank') {
                $payoutBankDebit += (int) ($line['debit_minor'] ?? 0);
            }
            if ($kind === 'application_fee_expense') {
                $applicationFeeExpense += (int) ($line['debit_minor'] ?? 0);
            }
            if ($kind === 'stripe_processing_fee_expense') {
                $stripeFeeExpense += (int) ($line['debit_minor'] ?? 0);
            }
            if ($kind === 'external_ticket_sales') {
                $externalSalesCredit += (int) ($line['credit_minor'] ?? 0);
            }
        }

        if ($legacy) {
            foreach ($lines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $desc = (string) ($line['description'] ?? '');
                if (str_contains($desc, '(bank)') && (int) ($line['debit_minor'] ?? 0) > 0) {
                    $payoutBankDebit += (int) ($line['debit_minor'] ?? 0);
                }
                if (str_contains($desc, '(expense)') && (
                    str_contains($desc, 'Stripe fees payout')
                    || str_contains($desc, 'Stripe processing fee payout')
                )) {
                    $stripeFeeExpense += (int) ($line['debit_minor'] ?? 0);
                }
            }
        }

        return [
            'debit_total' => $debitTotal,
            'credit_total' => $creditTotal,
            'payout_bank_debit' => $payoutBankDebit,
            'application_fee_expense_debit' => $applicationFeeExpense,
            'stripe_processing_fee_expense_debit' => $stripeFeeExpense,
            'external_ticket_sales_credit' => $externalSalesCredit,
            'legacy_without_line_kind' => $legacy,
        ];
    }

    protected function mirrorExternalTicketTotalMinor(
        Store $store,
        TripletexIntegration $integration,
        StoreStripePayout $payout,
    ): int {
        if (! TripletexLedgerSettings::externalTicketSalesEnabled($integration)) {
            return 0;
        }

        $rows = StoreStripeBalanceTransaction::query()
            ->where('store_id', $store->getKey())
            ->where('stripe_payout_id', $payout->stripe_payout_id)
            ->where('type', 'charge')
            ->get();

        $chargeIds = $rows->pluck('stripe_charge_id')->filter()->unique()->values();
        if ($chargeIds->isEmpty()) {
            return 0;
        }

        $charges = ConnectedCharge::query()
            ->where('stripe_account_id', (string) $store->stripe_account_id)
            ->whereIn('stripe_charge_id', $chargeIds->all())
            ->get()
            ->keyBy('stripe_charge_id');

        $total = 0;
        foreach ($rows as $bt) {
            if (empty($bt->stripe_charge_id)) {
                continue;
            }
            $charge = $charges->get($bt->stripe_charge_id);
            if (! $charge instanceof ConnectedCharge || $charge->pos_session_id !== null) {
                continue;
            }
            if (! TripletexExternalTicketSalesMatch::matches($integration, $charge, $bt)) {
                continue;
            }
            $total += max(0, (int) $charge->amount - (int) $charge->amount_refunded);
        }

        return $total;
    }

    /**
     * @param  list<string>  $messages
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function result(string $status, array $messages, array $extra = []): array
    {
        return array_merge([
            'status' => $status,
            'messages' => $messages,
        ], $extra);
    }
}
