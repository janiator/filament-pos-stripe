<?php

use App\Enums\AddonType;
use App\Enums\PowerOfficeMappingBasis;
use App\Enums\PowerOfficeSyncRunStatus;
use App\Models\Addon;
use App\Models\ConnectedCharge;
use App\Models\PosEvent;
use App\Models\PosSession;
use App\Models\PowerOfficeAccountMapping;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Services\PowerOffice\PowerOfficeZReportSync;
use App\Services\ZReport\ZReportPdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'poweroffice.client_id' => 'test-poweroffice-application-key',
        'poweroffice.subscription_key' => 'test-ocp-apim-subscription-key',
    ]);

    $pdf = \Mockery::mock(ZReportPdfGenerator::class);
    $pdf->shouldReceive('render')->zeroOrMoreTimes()->andReturn("%PDF-1.3 fake\n");
    $pdf->shouldReceive('suggestedFilename')->zeroOrMoreTimes()->andReturn('Z-test.pdf');
    $this->instance(ZReportPdfGenerator::class, $pdf);
});

function fakePowerOfficeLedgerHttp(): void
{
    Http::fake(function (Request $request) {
        if (preg_match('#/OAuth/Token#i', $request->url()) || str_contains(strtolower($request->url()), 'oauth/token')) {
            return Http::response([
                'access_token' => 'fake-poweroffice-access-token',
                'expires_in' => 3600,
            ], 200);
        }

        if (str_contains($request->url(), 'GeneralLedgerAccounts')) {
            return Http::response([
                ['Id' => 101, 'AccountNo' => 3000, 'VatCodeId' => 1],
                ['Id' => 102, 'AccountNo' => 2700, 'VatCodeId' => 1],
                ['Id' => 103, 'AccountNo' => 1920, 'VatCodeId' => null],
                ['Id' => 104, 'AccountNo' => 1921, 'VatCodeId' => null],
            ], 200);
        }

        if (str_contains($request->url(), 'VoucherDocumentation')) {
            return Http::response('', 204);
        }

        return Http::response([
            'Id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'VoucherNo' => 42,
        ], 201);
    });
}

it('creates a successful sync run when mapping exists and API succeeds', function () {
    fakePowerOfficeLedgerHttp();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'auto_sync_on_z_report' => true,
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Vat,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
        'card_clearing_account_no' => '1921',
    ]);

    $zReport = [
        'net_amount' => 10000,
        'vat_amount' => 2000,
        'vat_rate' => 25,
        'total_tips' => 0,
        'net_cash_amount' => 10000,
        'net_card_amount' => 0,
        'net_mobile_amount' => 0,
        'net_other_amount' => 0,
        'store' => ['id' => $store->id, 'name' => $store->name],
    ];

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => ['z_report_data' => $zReport],
    ]);

    $sync = app(PowerOfficeZReportSync::class);
    $ok = $sync->sync($session->id, true);

    expect($ok)->toBeTrue();

    $run = \App\Models\PowerOfficeSyncRun::query()
        ->where('pos_session_id', $session->id)
        ->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(PowerOfficeSyncRunStatus::Success)
        ->and($run->journal_voucher_no)->toBe(42);
});

it('does not sync when add-on is inactive', function () {
    $store = Store::factory()->create();
    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closing_data' => ['z_report_data' => ['net_amount' => 1]],
    ]);

    $sync = app(PowerOfficeZReportSync::class);
    expect($sync->sync($session->id, true))->toBeFalse();
});

it('is idempotent for the same session', function () {
    fakePowerOfficeLedgerHttp();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Vat,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
        'card_clearing_account_no' => '1921',
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => [
            'z_report_data' => [
                'net_amount' => 5000,
                'vat_amount' => 1000,
                'vat_rate' => 25,
                'total_tips' => 0,
                'net_cash_amount' => 5000,
                'net_card_amount' => 0,
                'net_mobile_amount' => 0,
                'net_other_amount' => 0,
                'store' => ['id' => $store->id, 'name' => $store->name],
            ],
        ],
    ]);

    $sync = app(PowerOfficeZReportSync::class);
    $sync->sync($session->id, true);
    $sync->sync($session->id, true);

    expect(\App\Models\PowerOfficeSyncRun::query()->where('pos_session_id', $session->id)->count())->toBe(1);
});

it('dispatches sync job when a Z-report event is created and integration is ready', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);

    PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'auto_sync_on_z_report' => true,
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closing_data' => [
            'z_report_data' => [
                'transactions_count' => 1,
                'net_amount' => 1000,
            ],
        ],
    ]);

    PosEvent::create([
        'store_id' => $store->id,
        'pos_session_id' => $session->id,
        'user_id' => \App\Models\User::factory()->create()->id,
        'event_code' => PosEvent::EVENT_Z_REPORT,
        'event_type' => 'report',
        'description' => 'Z',
        'event_data' => [],
        'occurred_at' => now(),
    ]);

    \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SyncPowerOfficeZReportJob::class);
});

it('dispatches sync job automatically when a session is closed from POS flow', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);

    PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'auto_sync_on_z_report' => true,
        'sync_enabled' => true,
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'open',
        'closed_at' => null,
    ]);

    ConnectedCharge::factory()->create([
        'pos_session_id' => $session->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'status' => 'succeeded',
        'payment_method' => 'cash',
        'amount' => 1000,
        'amount_refunded' => 0,
        'tip_amount' => 0,
    ]);

    expect($session->close())->toBeTrue();

    expect(PosEvent::query()
        ->where('pos_session_id', $session->id)
        ->where('event_code', PosEvent::EVENT_Z_REPORT)
        ->exists())->toBeTrue();

    \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SyncPowerOfficeZReportJob::class);
});

it('does not dispatch sync when sync_enabled is false', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);

    PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'auto_sync_on_z_report' => true,
        'sync_enabled' => false,
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closing_data' => [
            'z_report_data' => [
                'transactions_count' => 1,
                'net_amount' => 1000,
            ],
        ],
    ]);

    PosEvent::create([
        'store_id' => $store->id,
        'pos_session_id' => $session->id,
        'user_id' => \App\Models\User::factory()->create()->id,
        'event_code' => PosEvent::EVENT_Z_REPORT,
        'event_type' => 'report',
        'description' => 'Z',
        'event_data' => [],
        'occurred_at' => now(),
    ]);

    \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\SyncPowerOfficeZReportJob::class);
});

it('does not dispatch sync job when z-report is not eligible', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);

    PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'auto_sync_on_z_report' => true,
        'sync_enabled' => true,
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closing_data' => [
            'z_report_data' => [
                'transactions_count' => 0,
                'net_amount' => 0,
            ],
        ],
    ]);

    PosEvent::create([
        'store_id' => $store->id,
        'pos_session_id' => $session->id,
        'user_id' => \App\Models\User::factory()->create()->id,
        'event_code' => PosEvent::EVENT_Z_REPORT,
        'event_type' => 'report',
        'description' => 'Z',
        'event_data' => [],
        'occurred_at' => now(),
    ]);

    \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\SyncPowerOfficeZReportJob::class);
});

it('fails the sync run with a clear message when PowerOffice base URL is empty', function () {
    config(['poweroffice.base_urls.dev' => '']);

    Http::fake();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Vat,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
        'card_clearing_account_no' => '1921',
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => [
            'z_report_data' => [
                'net_amount' => 1000,
                'vat_amount' => 250,
                'vat_rate' => 25,
                'total_tips' => 0,
                'net_cash_amount' => 1000,
                'net_card_amount' => 0,
                'net_mobile_amount' => 0,
                'net_other_amount' => 0,
                'store' => ['id' => $store->id, 'name' => $store->name],
            ],
        ],
    ]);

    $sync = app(PowerOfficeZReportSync::class);
    expect($sync->sync($session->id, true))->toBeFalse();

    $run = \App\Models\PowerOfficeSyncRun::query()
        ->where('pos_session_id', $session->id)
        ->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(PowerOfficeSyncRunStatus::Failed)
        ->and($run->error_message)->toContain('PowerOffice base URL is empty');

    Http::assertNothingSent();
});

it('does not sync when sync_enabled is false even when forced', function () {
    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'sync_enabled' => false,
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Vat,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
        'card_clearing_account_no' => '1921',
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => [
            'z_report_data' => [
                'net_amount' => 1000,
                'vat_amount' => 250,
                'vat_rate' => 25,
                'total_tips' => 0,
                'net_cash_amount' => 1000,
                'net_card_amount' => 0,
                'net_mobile_amount' => 0,
                'net_other_amount' => 0,
                'store' => ['id' => $store->id, 'name' => $store->name],
            ],
        ],
    ]);

    $sync = app(PowerOfficeZReportSync::class);
    expect($sync->sync($session->id, true))->toBeFalse();
});
