<?php

namespace App\Services\Tripletex;

use App\Models\ConnectedCharge;
use App\Models\Store;
use App\Models\StoreStripeBalanceTransaction;
use App\Models\StoreStripePayout;
use App\Models\TripletexIntegration;
use App\Support\Tripletex\TripletexLedgerSettings;
use Carbon\Carbon;

class TripletexPayoutLedgerPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Store $store, TripletexIntegration $integration, StoreStripePayout $payout): array
    {
        $payoutAccounts = TripletexLedgerSettings::payoutAccounts($integration);
        if (! $payoutAccounts['credit'] || ! $payoutAccounts['debit']) {
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

        return [
            'source' => 'positiv_stripe_payout_tripletex',
            'store_stripe_payout_id' => $payout->id,
            'stripe_payout_id' => $payout->stripe_payout_id,
            'document_date' => $postingDate,
            'description' => 'Stripe payout '.$payout->stripe_payout_id,
            'currency' => strtoupper((string) $payout->currency),
            'lines' => $lines,
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
            if ($r->type !== 'charge') {
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

        $chargeIds = $rows->where('type', 'charge')->pluck('stripe_charge_id')->filter()->unique()->values();
        if ($chargeIds->isEmpty()) {
            return [];
        }

        $stripeAccountId = (string) $store->stripe_account_id;
        $charges = ConnectedCharge::query()
            ->where('stripe_account_id', $stripeAccountId)
            ->whereIn('stripe_charge_id', $chargeIds->all())
            ->get()
            ->keyBy('stripe_charge_id');

        $lines = [];
        foreach ($rows as $bt) {
            if ($bt->type !== 'charge' || empty($bt->stripe_charge_id)) {
                continue;
            }
            $charge = $charges->get($bt->stripe_charge_id);
            if (! $charge instanceof ConnectedCharge) {
                continue;
            }
            if ($charge->pos_session_id !== null) {
                continue;
            }
            if (! $this->externalTicketRulesMatch($integration, $charge, $bt)) {
                continue;
            }

            $amountMinor = max(0, (int) $charge->amount - (int) $charge->amount_refunded);
            if ($amountMinor <= 0) {
                continue;
            }

            $postingDate = $this->postingDateFromBalanceTransaction($bt);
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

    protected function externalTicketRulesMatch(
        TripletexIntegration $integration,
        ConnectedCharge $charge,
        StoreStripeBalanceTransaction $bt,
    ): bool {
        $meta = $charge->metadata;
        if (! is_array($meta) || $meta === []) {
            $meta = $bt->source_metadata;
        }
        if (! is_array($meta)) {
            $meta = [];
        }

        foreach (TripletexLedgerSettings::externalTicketSalesRequireMetadataKeys($integration) as $key) {
            $v = $meta[$key] ?? null;
            if ($v === null || $v === '' || $v === []) {
                return false;
            }
        }

        $regex = TripletexLedgerSettings::externalTicketSalesDescriptionRegex($integration);
        if ($regex !== null) {
            $haystack = (string) ($charge->description ?? '');
            if (@preg_match($regex, $haystack) !== 1) {
                return false;
            }
        }

        return true;
    }
}
