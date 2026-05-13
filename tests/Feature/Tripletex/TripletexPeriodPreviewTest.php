<?php

use App\Enums\AddonType;
use App\Enums\PowerOfficeMappingBasis;
use App\Jobs\BuildTripletexPeriodPreviewJob;
use App\Models\Addon;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\TripletexAccountMapping;
use App\Models\TripletexIntegration;
use App\Services\Tripletex\TripletexPeriodPreviewService;
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
        ->and($state['result']['rollup']['z_reports']['ok'])->toBe(1);
});
