<?php

namespace App\Services\Tripletex;

use App\Models\ConnectedCharge;
use App\Models\Store;
use App\Models\StoreStripeBalanceTransaction;
use App\Models\StoreStripePayout;
use App\Models\TripletexIntegration;
use App\Support\Tripletex\TripletexExternalTicketSalesMatch;
use App\Support\Tripletex\TripletexLedgerSettings;
use Carbon\Carbon;

class TripletexPayoutLedgerPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(
        Store $store,
        TripletexIntegration $integration,
        StoreStripePayout $payout,
        bool $skipPayoutBankTransfer = false,
    ): array {
        $payoutAccounts = TripletexLedgerSettings::payoutAccounts($integration);
        if (! $skipPayoutBankTransfer && (! $payoutAccounts['credit'] || ! $payoutAccounts['debit'])) {
            throw new \InvalidArgumentException('Tripletex ledger routing: payout credit and debit bank accounts must be configured.');
        }

        $amountMinor = (int) $payout->amount;
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Payout amount must be positive.');
        }

        $arrival = $payout->arrival_date ?? $payout->updated_at ?? now();
        $postingDate = $arrival instanceof \DateTimeInterface
            ? Carbon::instance($arrival)->format('Y-m-d')
            : now()->format('Y-m-d');

        $lines = [];
        if (! $skipPayoutBankTransfer) {
            $lines = [
                [
                    'account' => $payoutAccounts['debit'],
                    'debit_minor' => $amountMinor,
                    'credit_minor' => 0,
                    'posting_date' => $postingDate,
                    'description' => 'Stripe payout '.$payout->stripe_payout_id.' (bank)',
                    'line_kind' => 'payout_bank',
                ],
                [
                    'account' => $payoutAccounts['credit'],
                    'debit_minor' => 0,
                    'credit_minor' => $amountMinor,
                    'posting_date' => $postingDate,
                    'description' => 'Stripe payout '.$payout->stripe_payout_id.' (clearing)',
                    'line_kind' => 'payout_clearing',
                ],
            ];
        }

        $rows = StoreStripeBalanceTransaction::query()
            ->where('store_id', $store->getKey())
            ->where('stripe_payout_id', $payout->stripe_payout_id)
            ->orderBy('stripe_created')
            ->get();

        $breakdown = $this->feeBreakdownFromMirrorRows($rows);
        $feePostingDate = $this->feePostingDateFromRows($rows, $postingDate);

        $feeAccounts = TripletexLedgerSettings::paymentFeeAccounts($integration);
        $appFeeDebit = TripletexLedgerSettings::applicationFeeDebitAccount($integration);
        $supplierId = TripletexLedgerSettings::appFeeSupplierId($integration);

        if ($breakdown['application_fee_minor'] > 0 && $appFeeDebit && $feeAccounts['credit'] && $feeAccounts['debit']) {
            $app = $breakdown['application_fee_minor'];
            $lines[] = [
                'account' => $feeAccounts['credit'],
                'debit_minor' => 0,
                'credit_minor' => $app,
                'posting_date' => $feePostingDate,
                'description' => 'Stripe application fee payout '.$payout->stripe_payout_id.' (clearing)',
                'line_kind' => 'application_fee_clearing',
            ];
            $appExpense = [
                'account' => $appFeeDebit,
                'debit_minor' => $app,
                'credit_minor' => 0,
                'posting_date' => $feePostingDate,
                'description' => 'Stripe application fee payout '.$payout->stripe_payout_id.' (expense)',
                'line_kind' => 'application_fee_expense',
            ];
            if ($supplierId !== null) {
                $appExpense['tripletex_supplier_id'] = $supplierId;
            }
            $lines[] = $appExpense;
        }

        $stripeFee = $breakdown['stripe_fee_minor'];
        if ($stripeFee > 0 && $feeAccounts['credit'] && $feeAccounts['debit']) {
            $lines[] = [
                'account' => $feeAccounts['credit'],
                'debit_minor' => 0,
                'credit_minor' => $stripeFee,
                'posting_date' => $feePostingDate,
                'description' => 'Stripe processing fee payout '.$payout->stripe_payout_id.' (clearing)',
                'line_kind' => 'stripe_processing_fee_clearing',
            ];
            $lines[] = [
                'account' => $feeAccounts['debit'],
                'debit_minor' => $stripeFee,
                'credit_minor' => 0,
                'posting_date' => $feePostingDate,
                'description' => 'Stripe processing fee payout '.$payout->stripe_payout_id.' (expense)',
                'line_kind' => 'stripe_processing_fee_expense',
            ];
        }

        if (TripletexLedgerSettings::externalTicketSalesEnabled($integration)) {
            $lines = array_merge($lines, $this->externalTicketLines($store, $integration, $payout, $rows));
        }

        if ($lines === []) {
            throw new \InvalidArgumentException(
                'Tripletex payout voucher would be empty with clearing-to-bank transfer skipped. This payout has no application fees, Stripe processing fees, or external ticket lines to post; leave the option off or add mirror balance transactions first.',
            );
        }

        return [
            'source' => 'positiv_stripe_payout_tripletex',
            'store_stripe_payout_id' => $payout->id,
            'stripe_payout_id' => $payout->stripe_payout_id,
            'document_date' => $postingDate,
            'description' => 'Stripe payout '.$payout->stripe_payout_id,
            'currency' => strtoupper((string) $payout->currency),
            'skip_payout_bank_transfer' => $skipPayoutBankTransfer,
            'lines' => $lines,
        ];
    }

    /**
     * Explains why external (web/advance) ticket lines may be missing from a payout voucher preview.
     *
     * @return array<string, mixed>
     */
    public function externalTicketSalesDiagnostics(
        Store $store,
        TripletexIntegration $integration,
        StoreStripePayout $payout,
    ): array {
        $enabled = TripletexLedgerSettings::externalTicketSalesEnabled($integration);
        $salesAccount = TripletexLedgerSettings::externalTicketSalesAccountNo($integration);
        $clearingAccount = TripletexLedgerSettings::externalTicketSalesClearingAccountNo($integration);
        $explicitMetadataKeys = TripletexLedgerSettings::externalTicketSalesExplicitRequireMetadataKeys($integration);
        $defaultAnyOfKeys = TripletexLedgerSettings::externalTicketSalesDefaultAnyOfKeys();
        $hasRegex = TripletexLedgerSettings::externalTicketSalesDescriptionRegex($integration) !== null;

        $rows = StoreStripeBalanceTransaction::query()
            ->where('store_id', $store->getKey())
            ->where('stripe_payout_id', $payout->stripe_payout_id)
            ->orderBy('stripe_created')
            ->get();

        $chargeRows = 0;
        $missingConnectedCharge = 0;
        $linkedPosSession = 0;
        $webCandidates = 0;
        $zeroNetAmount = 0;
        $failedRules = 0;
        $matched = 0;

        $chargeIds = $rows->filter(fn (StoreStripeBalanceTransaction $r): bool => $r->isPayoutSaleSourceMirrorRow())
            ->pluck('stripe_charge_id')
            ->filter()
            ->unique()
            ->values();
        $stripeAccountId = (string) $store->stripe_account_id;
        $charges = ConnectedCharge::query()
            ->where('stripe_account_id', $stripeAccountId)
            ->whereIn('stripe_charge_id', $chargeIds->all())
            ->get()
            ->keyBy('stripe_charge_id');

        foreach ($rows as $bt) {
            if (! $bt->isPayoutSaleSourceMirrorRow()) {
                continue;
            }
            $chargeRows++;
            $charge = $charges->get($bt->stripe_charge_id);
            if (! $charge instanceof ConnectedCharge) {
                $missingConnectedCharge++;

                continue;
            }
            if ($charge->pos_session_id !== null) {
                $linkedPosSession++;

                continue;
            }
            $webCandidates++;
            if (! TripletexExternalTicketSalesMatch::matches($integration, $charge, $bt)) {
                $failedRules++;

                continue;
            }
            $amountMinor = max(0, (int) $charge->amount - (int) $charge->amount_refunded);
            if ($amountMinor <= 0) {
                $zeroNetAmount++;

                continue;
            }
            $matched++;
        }

        $notes = [];
        if (! $enabled) {
            $notes[] = 'External ticket sales on Stripe payout vouchers is disabled. Turn it on under Tripletex ledger settings (with a sales account) to add web/advance ticket lines. Ticket revenue from the POS is posted on Z-report vouchers when a session closes, not on payout vouchers.';
        } elseif (! $salesAccount || ! $clearingAccount) {
            $notes[] = 'External ticket sales is enabled but a sales or clearing account is missing; configure sales (and optionally clearing) account numbers.';
        } elseif ($chargeRows === 0) {
            $notes[] = 'No charge- or payment-type balance transactions with a source id (ch_/py_) are mirrored for this payout yet. Sync Stripe balance transactions for this payout or the store, then preview again.';
        } elseif ($webCandidates === 0 && $chargeRows > 0) {
            $notes[] = 'Every mirrored sale in this payout is linked to a POS session (pos_session_id is set). Payout ticket lines only apply to web or advance sales without a POS session. Use the Z-report Tripletex preview for till ticket revenue.';
        } elseif ($webCandidates > 0 && $matched === 0) {
            if ($explicitMetadataKeys !== null) {
                $keys = 'all of: '.implode(', ', $explicitMetadataKeys);
            } else {
                $keys = 'at least one of (default): '.implode(', ', $defaultAnyOfKeys);
            }
            $notes[] = "This payout has {$webCandidates} sale(s) without a POS session, but none matched external-ticket rules (metadata: {$keys}".($hasRegex ? '; description must match the configured regex' : '').').';
        }

        return [
            'enabled' => $enabled,
            'sales_account_configured' => filled($salesAccount),
            'clearing_account_configured' => filled($clearingAccount),
            'required_metadata_keys' => $explicitMetadataKeys ?? [],
            'default_any_of_metadata_keys' => $explicitMetadataKeys === null ? $defaultAnyOfKeys : null,
            'description_regex_configured' => $hasRegex,
            'charge_balance_transactions' => $chargeRows,
            'connected_charge_rows_loaded' => (int) $charges->count(),
            'charges_without_pos_session' => $webCandidates,
            'matched_for_voucher_lines' => $matched,
            'skipped_no_connected_charge' => $missingConnectedCharge,
            'skipped_linked_pos_session' => $linkedPosSession,
            'skipped_zero_net_amount' => $zeroNetAmount,
            'skipped_metadata_or_regex' => $failedRules,
            'notes' => $notes,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, StoreStripeBalanceTransaction>  $rows
     * @return array{application_fee_minor: int, stripe_fee_minor: int, total_fee_minor: int}
     */
    protected function feeBreakdownFromMirrorRows(\Illuminate\Support\Collection $rows): array
    {
        $totalFee = (int) $rows->sum('fee');

        $appFromSeparateBt = abs((int) $rows->where('type', 'application_fee')->sum('fee'));

        $appFromDetails = 0;
        $hasFeeDetailsOnCharge = false;

        foreach ($rows as $r) {
            if (! in_array($r->type, ['charge', 'payment'], true)) {
                continue;
            }
            $details = $r->fee_details;
            if (! is_array($details) || $details === []) {
                continue;
            }
            $hasFeeDetailsOnCharge = true;
            foreach ($details as $fd) {
                if (! is_array($fd)) {
                    continue;
                }
                if (($fd['type'] ?? '') === 'application_fee') {
                    $appFromDetails += abs((int) ($fd['amount'] ?? 0));
                }
            }
        }

        $appFee = min($totalFee, $appFromSeparateBt + $appFromDetails);
        if (! $hasFeeDetailsOnCharge && $appFromSeparateBt === 0) {
            $appFee = 0;
        }

        $stripeFee = max(0, $totalFee - $appFee);

        return [
            'application_fee_minor' => $appFee,
            'stripe_fee_minor' => $stripeFee,
            'total_fee_minor' => $totalFee,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, StoreStripeBalanceTransaction>  $rows
     */
    protected function feePostingDateFromRows(\Illuminate\Support\Collection $rows, string $fallback): string
    {
        $minTs = null;
        foreach ($rows as $r) {
            if ((int) $r->fee <= 0) {
                continue;
            }
            $ts = (int) ($r->stripe_created ?? 0);
            if ($ts > 0 && ($minTs === null || $ts < $minTs)) {
                $minTs = $ts;
            }
        }
        if ($minTs === null) {
            return $fallback;
        }

        return Carbon::createFromTimestamp($minTs)->timezone((string) config('app.timezone'))->format('Y-m-d');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, StoreStripeBalanceTransaction>  $rows
     * @return list<array<string, mixed>>
     */
    protected function externalTicketLines(
        Store $store,
        TripletexIntegration $integration,
        StoreStripePayout $payout,
        \Illuminate\Support\Collection $rows,
    ): array {
        $salesAccount = TripletexLedgerSettings::externalTicketSalesAccountNo($integration);
        $clearingAccount = TripletexLedgerSettings::externalTicketSalesClearingAccountNo($integration);
        if (! $salesAccount || ! $clearingAccount) {
            return [];
        }

        $chargeIds = $rows->filter(fn (StoreStripeBalanceTransaction $r): bool => $r->isPayoutSaleSourceMirrorRow())
            ->pluck('stripe_charge_id')
            ->filter()
            ->unique()
            ->values();
        if ($chargeIds->isEmpty()) {
            return [];
        }

        $stripeAccountId = (string) $store->stripe_account_id;
        $charges = ConnectedCharge::query()
            ->where('stripe_account_id', $stripeAccountId)
            ->whereIn('stripe_charge_id', $chargeIds->all())
            ->get()
            ->keyBy('stripe_charge_id');

        $amountsByPostingDate = [];
        foreach ($rows as $bt) {
            if (! $bt->isPayoutSaleSourceMirrorRow()) {
                continue;
            }
            $charge = $charges->get($bt->stripe_charge_id);
            if (! $charge instanceof ConnectedCharge) {
                continue;
            }
            if ($charge->pos_session_id !== null) {
                continue;
            }
            if (! TripletexExternalTicketSalesMatch::matches($integration, $charge, $bt)) {
                continue;
            }

            $amountMinor = max(0, (int) $charge->amount - (int) $charge->amount_refunded);
            if ($amountMinor <= 0) {
                continue;
            }

            $postingDate = $this->postingDateFromBalanceTransaction($bt);
            $amountsByPostingDate[$postingDate] = ($amountsByPostingDate[$postingDate] ?? 0) + $amountMinor;
        }

        if ($amountsByPostingDate === []) {
            return [];
        }

        ksort($amountsByPostingDate);

        $lines = [];
        foreach ($amountsByPostingDate as $postingDate => $amountMinor) {
            $vatTypeId = TripletexLedgerSettings::externalTicketSalesVatTypeId($integration);

            $desc = 'Salg billetter (forhånd / web) '.$payout->stripe_payout_id;
            $clearDesc = 'Bank inntekt (forhånd / web) '.$payout->stripe_payout_id;

            $salesLine = [
                'account' => $salesAccount,
                'debit_minor' => 0,
                'credit_minor' => $amountMinor,
                'posting_date' => $postingDate,
                'description' => $desc,
                'line_kind' => 'external_ticket_sales',
            ];
            if ($vatTypeId !== null) {
                $salesLine['tripletex_vat_type_id'] = $vatTypeId;
            }
            $lines[] = $salesLine;

            $lines[] = [
                'account' => $clearingAccount,
                'debit_minor' => $amountMinor,
                'credit_minor' => 0,
                'posting_date' => $postingDate,
                'description' => $clearDesc,
                'line_kind' => 'external_ticket_clearing',
            ];
        }

        return $lines;
    }

    protected function postingDateFromBalanceTransaction(StoreStripeBalanceTransaction $bt): string
    {
        $ts = (int) ($bt->stripe_created ?? 0);
        if ($ts <= 0) {
            return now()->format('Y-m-d');
        }

        return Carbon::createFromTimestamp($ts)->timezone((string) config('app.timezone'))->format('Y-m-d');
    }
}
