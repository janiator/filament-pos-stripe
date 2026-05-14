<?php

use App\Enums\AddonType;
use App\Enums\PowerOfficeMappingBasis;
use App\Jobs\BuildTripletexPeriodPreviewJob;
use App\Models\Addon;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\StoreStripePayout;
use App\Models\TripletexAccountMapping;
use App\Models\TripletexIntegration;
use App\Services\Tripletex\TripletexPeriodPreviewService;
use App\Services\Tripletex\TripletexSyncPreviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('marks z_reports_truncated when more sessions exist than limit_z', function () {
    $store = Store::factory()->create();

    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
                'payout' => [
                    'credit_account_no' => '1901',
                    'debit_bank_account_no' => '1920',
                ],
            ],
        ],
    ]);

    TripletexAccountMapping::factory()->create([
        'store_id' => $store->id,
        'tripletex_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Vat,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
        'card_clearing_account_no' => '1921',
    ]);

    $zReport = [
        'net_amount' => 100,
        'vat_amount' => 25,
        'vat_rate' => 25,
        'total_tips' => 0,
        'net_cash_amount' => 100,
        'net_card_amount' => 0,
        'net_mobile_amount' => 0,
        'net_other_amount' => 0,
        'store' => ['id' => $store->id, 'name' => $store->name],
    ];

    foreach ([1, 2, 3] as $day) {
        PosSession::factory()->create([
            'store_id' => $store->id,
            'status' => 'closed',
            'closed_at' => Carbon::parse("2026-04-{$day} 10:00:00"),
            'closing_data' => ['z_report_data' => $zReport],
        ]);
    }

    $out = app(TripletexPeriodPreviewService::class)->previewPeriod(
        $store,
        $integration,
        Carbon::parse('2026-04-01')->startOfDay(),
        Carbon::parse('2026-04-30')->endOfDay(),
        false,
        2,
        10,
        false,
    );

    expect($out['limits']['z_reports_total_in_period'])->toBe(3)
        ->and($out['limits']['z_reports_truncated'])->toBeTrue()
        ->and(count($out['z_reports']))->toBe(2);
});

it('writes completed period preview to tripletex integration when job finishes', function () {
    $store = Store::factory()->create();

    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
                'payout' => [
                    'credit_account_no' => '1901',
                    'debit_bank_account_no' => '1920',
                ],
            ],
        ],
    ]);

    $integration = TripletexIntegration::query()->where('store_id', $store->id)->firstOrFail();

    TripletexAccountMapping::factory()->create([
        'store_id' => $store->id,
        'tripletex_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Vat,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
        'card_clearing_account_no' => '1921',
    ]);

    $zReport = [
        'net_amount' => 100,
        'vat_amount' => 25,
        'vat_rate' => 25,
        'total_tips' => 0,
        'net_cash_amount' => 100,
        'net_card_amount' => 0,
        'net_mobile_amount' => 0,
        'net_other_amount' => 0,
        'store' => ['id' => $store->id, 'name' => $store->name],
    ];

    PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => Carbon::parse('2026-04-10 10:00:00'),
        'closing_data' => ['z_report_data' => $zReport],
    ]);

    Bus::dispatchSync(new BuildTripletexPeriodPreviewJob(
        $store->id,
        '2026-04-01',
        '2026-04-30',
        false,
        10,
        10,
        false,
    ));

    $integration->refresh();
    $state = $integration->period_preview_state;

    expect($state)->toBeArray()
        ->and($state['status'])->toBe('complete')
        ->and($state['result']['ok'])->toBeTrue()
        ->and($state['result']['rollup']['z_reports']['ok'])->toBe(1)
        ->and($state['result']['rollup'])->toHaveKey('reconciliation')
        ->and($state['result']['aggregate_vouchers']['z_reports']['ok'] ?? false)->toBeTrue()
        ->and($state['storage_meta'])->toBeArray()
        ->and($state['storage_meta'])->toHaveKeys(['steps', 'approx_bytes_before', 'approx_bytes_after', 'max_bytes_target']);
});

it('builds aggregate Z voucher totals from merged successful session previews and skips payouts when none exist', function () {
    $store = Store::factory()->create();

    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
                'payout' => [
                    'credit_account_no' => '1901',
                    'debit_bank_account_no' => '1920',
                ],
            ],
        ],
    ]);

    TripletexAccountMapping::factory()->create([
        'store_id' => $store->id,
        'tripletex_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Vat,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
        'card_clearing_account_no' => '1921',
    ]);

    $zReport = [
        'net_amount' => 100,
        'vat_amount' => 25,
        'vat_rate' => 25,
        'total_tips' => 0,
        'net_cash_amount' => 100,
        'net_card_amount' => 0,
        'net_mobile_amount' => 0,
        'net_other_amount' => 0,
        'store' => ['id' => $store->id, 'name' => $store->name],
    ];

    $sessionA = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => Carbon::parse('2026-04-01 10:00:00'),
        'closing_data' => ['z_report_data' => $zReport],
    ]);

    $sessionB = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => Carbon::parse('2026-04-02 10:00:00'),
        'closing_data' => ['z_report_data' => $zReport],
    ]);

    $previewA = app(TripletexSyncPreviewService::class)->previewZReport($sessionA, $integration, false);
    $previewB = app(TripletexSyncPreviewService::class)->previewZReport($sessionB, $integration, false);
    expect($previewA['ok'] ?? false)->toBeTrue()
        ->and($previewB['ok'] ?? false)->toBeTrue();

    $sumLinesA = array_sum(array_map(fn (array $ln): int => (int) ($ln['debit_minor'] ?? 0), $previewA['lines']));
    $sumLinesB = array_sum(array_map(fn (array $ln): int => (int) ($ln['debit_minor'] ?? 0), $previewB['lines']));
    expect($sumLinesA)->toBe((int) $previewA['debit_total_minor'])
        ->and($sumLinesB)->toBe((int) $previewB['debit_total_minor']);

    $expectedDebit = (int) $previewA['debit_total_minor'] + (int) $previewB['debit_total_minor'];
    $expectedCredit = (int) $previewA['credit_total_minor'] + (int) $previewB['credit_total_minor'];

    $out = app(TripletexPeriodPreviewService::class)->previewPeriod(
        $store,
        $integration,
        Carbon::parse('2026-04-01')->startOfDay(),
        Carbon::parse('2026-04-30')->endOfDay(),
        false,
        10,
        10,
        false,
    );

    expect(count($out['z_reports']))->toBe(2);

    $aggZ = $out['aggregate_vouchers']['z_reports'];
    expect($aggZ['ok'])->toBeTrue()
        ->and($aggZ['successful_previews_count'])->toBe(2)
        ->and($aggZ['preview']['debit_total_minor'])->toBe($expectedDebit)
        ->and($aggZ['preview']['credit_total_minor'])->toBe($expectedCredit);

    $aggP = $out['aggregate_vouchers']['payouts'];
    expect($aggP['ok'])->toBeFalse()
        ->and($aggP['successful_previews_count'])->toBe(0);
});

it('rollup reconciliation matches payout bank debits to store payout amounts for successful previews', function () {
    $store = Store::factory()->create();

    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
                'payout' => [
                    'credit_account_no' => '1901',
                    'debit_bank_account_no' => '1920',
                ],
                'payment_fee' => [
                    'credit_account_no' => '1901',
                    'debit_account_no' => '7771',
                ],
            ],
        ],
    ]);

    TripletexAccountMapping::factory()->create([
        'store_id' => $store->id,
        'tripletex_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Vat,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
        'card_clearing_account_no' => '1921',
    ]);

    $arrival = Carbon::parse('2026-04-12 12:00:00');

    StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_period_recon_a',
        'amount' => 30_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => $arrival,
        'automatic' => true,
    ]));

    StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_period_recon_b',
        'amount' => 45_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => $arrival->copy()->addDay(),
        'automatic' => true,
    ]));

    $out = app(TripletexPeriodPreviewService::class)->previewPeriod(
        $store,
        $integration,
        Carbon::parse('2026-04-01')->startOfDay(),
        Carbon::parse('2026-04-30')->endOfDay(),
        false,
        10,
        10,
        false,
    );

    $rec = $out['rollup']['reconciliation'];
    expect($rec)->toBeArray()
        ->and($rec['all_ok'])->toBeTrue()
        ->and($rec['payout_bank_matches_store_payout_rows'])->toBeTrue()
        ->and($rec['payout_bank_debit_minor_previews'])->toBe(75_000)
        ->and($rec['store_payout_amount_minor_sum_ok_previews'])->toBe(75_000)
        ->and($rec['external_ticket_sales_mirror_ok'])->toBeTrue();
});
