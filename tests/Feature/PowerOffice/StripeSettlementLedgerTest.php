<?php

use App\Enums\PowerOfficeMappingBasis;
use App\Models\ConnectedCharge;
use App\Models\PosSession;
use App\Models\PowerOfficeAccountMapping;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Models\StoreStripeBalanceTransaction;
use App\Models\StoreStripePayout;
use App\Services\PowerOffice\PowerOfficeLedgerPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses synced Stripe balance transactions for fee lines when Z-report omits stripe_fees_minor', function () {
    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_settlement_test',
    ]);

    $closedAt = now()->startOfDay()->addHours(15);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => $closedAt,
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
                'payment_fee' => [
                    'credit_account_no' => '2900',
                    'debit_account_no' => '7900',
                ],
                'payment_debits' => [
                    'card' => '1921',
                ],
            ],
        ],
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'store_id' => $store->id,
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Vat,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
        'card_clearing_account_no' => '1921',
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'stripe_charge_id' => 'ch_fee_ledger_test',
        'status' => 'succeeded',
        'payment_method' => 'card',
        'amount' => 10_000,
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_fee_ledger_test',
        'type' => 'charge',
        'amount' => 10_000,
        'fee' => 275,
        'net' => 9_725,
        'currency' => 'nok',
        'status' => 'available',
        'stripe_charge_id' => 'ch_fee_ledger_test',
        'stripe_created' => (int) now()->subHour()->timestamp,
    ]);

    $zReport = [
        'net_amount' => 10_000,
        'vat_amount' => 2_500,
        'vat_rate' => 25,
        'total_tips' => 0,
        'by_payment_method_net' => [
            'card' => ['amount' => 10_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build(
        $session,
        $integration->fresh('accountMappings'),
        $zReport
    );

    $feeCredit = collect($payload['lines'])->first(fn (array $l): bool => $l['account'] === '2900' && $l['credit_minor'] === 275);
    $feeDebit = collect($payload['lines'])->first(fn (array $l): bool => $l['account'] === '7900' && $l['debit_minor'] === 275);

    expect($feeCredit)->not->toBeNull()
        ->and($feeDebit)->not->toBeNull();
});

it('uses synced Stripe payouts when Z-report omits payout_to_bank_minor', function () {
    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_payout_ledger_test',
    ]);

    $closedAt = now()->startOfDay()->addHours(10);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => $closedAt,
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
                'payout' => [
                    'credit_account_no' => '1925',
                    'debit_bank_account_no' => '1920',
                ],
                'payment_debits' => [
                    'cash' => '1920',
                ],
            ],
        ],
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'store_id' => $store->id,
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Vat,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
        'card_clearing_account_no' => '1921',
    ]);

    StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_payout_id' => 'po_ledger_test_1',
        'amount' => 88_800,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => $closedAt->copy()->startOfDay()->addHours(8),
        'automatic' => true,
        'stripe_created' => (int) $closedAt->subDay()->timestamp,
    ]);

    $zReport = [
        'net_amount' => 50_000,
        'vat_amount' => 12_500,
        'vat_rate' => 25,
        'total_tips' => 0,
        'by_payment_method_net' => [
            'cash' => ['amount' => 50_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build(
        $session,
        $integration->fresh('accountMappings'),
        $zReport
    );

    $payoutCredit = collect($payload['lines'])->first(fn (array $l): bool => $l['account'] === '1925' && $l['credit_minor'] === 88_800);
    $payoutDebit = collect($payload['lines'])->first(fn (array $l): bool => $l['account'] === '1920' && $l['debit_minor'] === 88_800);

    expect($payoutCredit)->not->toBeNull()
        ->and($payoutDebit)->not->toBeNull();
});

it('prefers Z-report stripe_fees_minor over database when positive', function () {
    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_override_fee',
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
                'payment_fee' => [
                    'credit_account_no' => '2900',
                    'debit_account_no' => '7900',
                ],
                'payment_debits' => [
                    'card' => '1921',
                ],
            ],
        ],
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'store_id' => $store->id,
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Vat,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
        'card_clearing_account_no' => '1921',
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'stripe_charge_id' => 'ch_override_fee',
        'status' => 'succeeded',
        'payment_method' => 'card',
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_override_fee',
        'type' => 'charge',
        'amount' => 5_000,
        'fee' => 99,
        'net' => 4_901,
        'currency' => 'nok',
        'status' => 'available',
        'stripe_charge_id' => 'ch_override_fee',
        'stripe_created' => (int) now()->timestamp,
    ]);

    $zReport = [
        'net_amount' => 5_000,
        'vat_amount' => 1_250,
        'vat_rate' => 25,
        'total_tips' => 0,
        'stripe_fees_minor' => 501,
        'by_payment_method_net' => [
            'card' => ['amount' => 5_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build(
        $session,
        $integration->fresh('accountMappings'),
        $zReport
    );

    $feeCredit = collect($payload['lines'])->first(fn (array $l): bool => $l['account'] === '2900' && $l['credit_minor'] > 0);

    expect($feeCredit)->not->toBeNull()
        ->and($feeCredit['credit_minor'])->toBe(501);
});
