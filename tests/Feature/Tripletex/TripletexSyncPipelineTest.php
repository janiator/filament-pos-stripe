<?php

use App\Actions\Stripe\SyncStoreStripeBalanceTransactionsFromStripe;
use App\Enums\AddonType;
use App\Enums\PowerOfficeMappingBasis;
use App\Enums\TripletexSyncRunStatus;
use App\Enums\TripletexSyncType;
use App\Jobs\SyncTripletexPayoutJob;
use App\Jobs\SyncTripletexZReportJob;
use App\Models\Addon;
use App\Models\ConnectedCharge;
use App\Models\PosDevice;
use App\Models\PosEvent;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\StoreStripeBalanceTransaction;
use App\Models\StoreStripePayout;
use App\Models\TripletexAccountMapping;
use App\Models\TripletexIntegration;
use App\Models\TripletexSyncRun;
use App\Models\User;
use App\Services\Tripletex\TripletexHistoricalSyncService;
use App\Services\Tripletex\TripletexManualVoucherPayloadFactory;
use App\Services\Tripletex\TripletexPayoutLedgerPayloadBuilder;
use App\Services\Tripletex\TripletexPayoutReconciliationService;
use App\Services\Tripletex\TripletexPayoutSync;
use App\Services\Tripletex\TripletexSyncPreviewService;
use App\Services\Tripletex\TripletexZReportLedgerPayloadBuilder;
use App\Services\Tripletex\TripletexZReportSync;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function tripletexFakeAccountNumberFromUrl(Request $request): int
{
    $qs = parse_url($request->url(), PHP_URL_QUERY) ?? '';
    parse_str($qs, $params);

    return (int) ($params['number'] ?? 0);
}

function fakeTripletexLedgerHttp(?callable $accountOverride = null): void
{
    Http::fake(function (Request $request) use ($accountOverride) {
        $url = $request->url();

        if (str_contains($url, 'token/session')) {
            return Http::response(['value' => ['token' => 'fake-tripletex-session']], 200);
        }

        if (str_contains($url, '/ledger/account')) {
            if ($accountOverride !== null) {
                $custom = $accountOverride($request);
                if ($custom !== null) {
                    return $custom;
                }
            }

            $number = tripletexFakeAccountNumberFromUrl($request);

            return Http::response([
                'values' => [
                    [
                        'id' => 100_000 + $number,
                        'number' => $number,
                        'name' => 'Account '.$number,
                    ],
                ],
            ], 200);
        }

        if (str_contains($url, '/ledger/voucher')) {
            return Http::response(['value' => ['id' => 55_001]], 201);
        }

        return Http::response(['message' => 'Unexpected URL in fake: '.$url], 404);
    });
}

it('creates a successful Tripletex Z-report sync run when mapping exists and API succeeds', function () {
    fakeTripletexLedgerHttp();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'auto_sync_on_z_report' => true,
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
        'net_amount' => 10_000,
        'vat_amount' => 2_000,
        'vat_rate' => 25,
        'total_tips' => 0,
        'net_cash_amount' => 10_000,
        'net_card_amount' => 0,
        'net_mobile_amount' => 0,
        'net_other_amount' => 0,
        'store' => ['id' => $store->id, 'name' => $store->name],
    ];

    $session = PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => ['z_report_data' => $zReport],
    ]);

    $sync = app(TripletexZReportSync::class);
    expect($sync->sync($session->id, true))->toBeTrue();

    $run = TripletexSyncRun::query()
        ->where('pos_session_id', $session->id)
        ->where('sync_type', TripletexSyncType::ZReport)
        ->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(TripletexSyncRunStatus::Success)
        ->and($run->tripletex_voucher_id)->toBe('55001');
});

it('is idempotent for the same Tripletex Z-report session', function () {
    fakeTripletexLedgerHttp();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
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

    $session = PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => [
            'z_report_data' => [
                'net_amount' => 5_000,
                'vat_amount' => 1_000,
                'vat_rate' => 25,
                'total_tips' => 0,
                'net_cash_amount' => 5_000,
                'net_card_amount' => 0,
                'net_mobile_amount' => 0,
                'net_other_amount' => 0,
                'store' => ['id' => $store->id, 'name' => $store->name],
            ],
        ],
    ]);

    $sync = app(TripletexZReportSync::class);
    $sync->sync($session->id, true);
    $sync->sync($session->id, true);

    expect(TripletexSyncRun::query()->where('pos_session_id', $session->id)->count())->toBe(1);
});

it('dispatches Tripletex Z-report job when Z-report event is created and integration is ready', function () {
    Queue::fake();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'auto_sync_on_z_report' => true,
        'sync_enabled' => true,
    ]);

    $session = PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closing_data' => [
            'z_report_data' => [
                'transactions_count' => 1,
                'net_amount' => 1_000,
            ],
        ],
    ]);

    PosEvent::create([
        'store_id' => $store->id,
        'pos_session_id' => $session->id,
        'user_id' => User::factory()->create()->id,
        'event_code' => PosEvent::EVENT_Z_REPORT,
        'event_type' => 'report',
        'description' => 'Z',
        'event_data' => [],
        'occurred_at' => now(),
    ]);

    Queue::assertPushed(SyncTripletexZReportJob::class);
});

it('does not dispatch Tripletex sync when sync_enabled is false', function () {
    Queue::fake();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'auto_sync_on_z_report' => true,
        'sync_enabled' => false,
    ]);

    $session = PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closing_data' => [
            'z_report_data' => [
                'transactions_count' => 1,
                'net_amount' => 1_000,
            ],
        ],
    ]);

    PosEvent::create([
        'store_id' => $store->id,
        'pos_session_id' => $session->id,
        'user_id' => User::factory()->create()->id,
        'event_code' => PosEvent::EVENT_Z_REPORT,
        'event_type' => 'report',
        'description' => 'Z',
        'event_data' => [],
        'occurred_at' => now(),
    ]);

    Queue::assertNotPushed(SyncTripletexZReportJob::class);
});

it('fails Tripletex Z-report sync when a ledger account is missing in Tripletex', function () {
    fakeTripletexLedgerHttp(function (Request $request) {
        if (! str_contains($request->url(), '/ledger/account')) {
            return null;
        }
        $number = tripletexFakeAccountNumberFromUrl($request);
        if ($number === 3_000) {
            return Http::response(['values' => []], 200);
        }

        return null;
    });

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
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

    $session = PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => [
            'z_report_data' => [
                'net_amount' => 1_000,
                'vat_amount' => 250,
                'vat_rate' => 25,
                'total_tips' => 0,
                'net_cash_amount' => 1_000,
                'net_card_amount' => 0,
                'net_mobile_amount' => 0,
                'net_other_amount' => 0,
                'store' => ['id' => $store->id, 'name' => $store->name],
            ],
        ],
    ]);

    $sync = app(TripletexZReportSync::class);
    expect($sync->sync($session->id, true))->toBeFalse();

    $run = TripletexSyncRun::query()
        ->where('pos_session_id', $session->id)
        ->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(TripletexSyncRunStatus::Failed)
        ->and($run->error_message)->toContain('3000');
});

it('creates a successful Tripletex payout sync when ledger routing and API succeed', function () {
    fakeTripletexLedgerHttp();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
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

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_test_tripletex_1',
        'amount' => 50_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_tripletex_fee_1',
        'type' => 'charge',
        'amount' => -100,
        'fee' => 100,
        'net' => -100,
        'currency' => 'nok',
        'status' => 'available',
        'stripe_payout_id' => 'po_test_tripletex_1',
        'stripe_created' => (int) now()->timestamp,
    ]);

    $sync = app(TripletexPayoutSync::class);
    expect($sync->sync($payout->id, true))->toBeTrue();

    $run = TripletexSyncRun::query()
        ->where('store_stripe_payout_id', $payout->id)
        ->where('sync_type', TripletexSyncType::Payout)
        ->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(TripletexSyncRunStatus::Success)
        ->and($run->tripletex_voucher_id)->toBe('55001');
});

it('is idempotent for the same Tripletex payout', function () {
    fakeTripletexLedgerHttp();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'settings' => [
            'ledger' => [
                'payout' => [
                    'credit_account_no' => '1901',
                    'debit_bank_account_no' => '1920',
                ],
            ],
        ],
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_test_tripletex_2',
        'amount' => 10_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    $sync = app(TripletexPayoutSync::class);
    $sync->sync($payout->id, true);
    $sync->sync($payout->id, true);

    expect(TripletexSyncRun::query()->where('store_stripe_payout_id', $payout->id)->count())->toBe(1);
});

it('resolves stripe payout id from Stripe balance transaction shapes', function () {
    $action = new SyncStoreStripeBalanceTransactionsFromStripe;
    $ref = new ReflectionClass($action);
    $method = $ref->getMethod('resolvePayoutId');
    $method->setAccessible(true);

    expect($method->invoke($action, (object) ['payout' => 'po_abc123']))->toBe('po_abc123');
    expect($method->invoke($action, (object) ['payout' => (object) ['id' => 'po_def456']]))->toBe('po_def456');
    expect($method->invoke($action, (object) ['payout' => null]))->toBeNull();
});

it('resolves py_ payment ids from Stripe charge balance transactions for payout mirror joins', function () {
    $action = new SyncStoreStripeBalanceTransactionsFromStripe;
    $ref = new ReflectionClass($action);
    $resolveChargeId = $ref->getMethod('resolveChargeId');
    $resolveChargeId->setAccessible(true);

    expect($resolveChargeId->invoke($action, (object) [
        'type' => 'charge',
        'source' => 'py_3TVtzBRu3Ljbb32R18cSlSBn',
    ]))->toBe('py_3TVtzBRu3Ljbb32R18cSlSBn');

    expect($resolveChargeId->invoke($action, (object) [
        'type' => 'charge',
        'source' => (object) [
            'id' => 'py_3abc',
            'object' => 'payment',
        ],
    ]))->toBe('py_3abc');

    expect($resolveChargeId->invoke($action, (object) [
        'type' => 'charge',
        'source' => (object) [
            'id' => 'ch_3abc',
            'object' => 'charge',
        ],
    ]))->toBe('ch_3abc');
});

it('extracts source metadata from payment balance transaction sources', function () {
    $action = new SyncStoreStripeBalanceTransactionsFromStripe;
    $ref = new ReflectionClass($action);
    $extract = $ref->getMethod('extractChargeSourceExtras');
    $extract->setAccessible(true);

    $bt = (object) [
        'type' => 'charge',
        'source' => (object) [
            'object' => 'payment',
            'id' => 'py_test',
            'metadata' => (object) ['booking_id' => '6887', 'seats' => 'A1'],
            'payment_intent' => 'pi_3TVtzBRu3Ljbb32R1VGaUNQC',
        ],
    ];

    $out = $extract->invoke($action, $bt);

    expect($out['source_metadata']['booking_id'])->toBe('6887')
        ->and($out['stripe_payment_intent_id'])->toBe('pi_3TVtzBRu3Ljbb32R1VGaUNQC');
});

it('queues Tripletex Z-report sync from the API when the add-on and integration are ready', function () {
    Queue::fake();

    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'sync_enabled' => true,
    ]);

    $session = PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => [
            'z_report_data' => [
                'transactions_count' => 1,
                'net_amount' => 500,
            ],
        ],
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/tripletex/sync/z-report/'.$session->id)
        ->assertOk()
        ->assertJsonPath('pos_session_id', $session->id);

    Queue::assertPushed(SyncTripletexZReportJob::class);
});

it('dispatches payout sync job when a paid payout is saved and auto_sync_payouts is on', function () {
    Queue::fake();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'sync_enabled' => true,
        'auto_sync_payouts' => true,
    ]);

    StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_observer_tripletex_1',
        'amount' => 5_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]);

    Queue::assertPushed(SyncTripletexPayoutJob::class);
});

it('returns a Z-report preview with ledger lines without posting', function () {
    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
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

    $session = PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => [
            'z_report_data' => [
                'net_amount' => 1_000,
                'vat_amount' => 250,
                'vat_rate' => 25,
                'total_tips' => 0,
                'net_cash_amount' => 1_000,
                'net_card_amount' => 0,
                'net_mobile_amount' => 0,
                'net_other_amount' => 0,
                'store' => ['id' => $store->id, 'name' => $store->name],
            ],
        ],
    ]);

    $preview = app(TripletexSyncPreviewService::class)->previewZReport($session, $integration, false);

    expect($preview['ok'])->toBeTrue()
        ->and($preview['lines'])->not->toBeEmpty()
        ->and($preview['lines_display'])->toBeArray()->not->toBeEmpty()
        ->and($preview['lines_display'][0])->toHaveKeys(['account', 'description', 'debit', 'credit', 'currency'])
        ->and($preview['tripletex_voucher_payload'])->toBeNull()
        ->and($preview['tripletex_postings_display'])->toBeNull();
});

it('includes tripletex voucher payload and postings display when Z preview resolves accounts', function () {
    fakeTripletexLedgerHttp();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
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

    $session = PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => [
            'z_report_data' => [
                'net_amount' => 1_000,
                'vat_amount' => 250,
                'vat_rate' => 25,
                'total_tips' => 0,
                'net_cash_amount' => 1_000,
                'net_card_amount' => 0,
                'net_mobile_amount' => 0,
                'net_other_amount' => 0,
                'store' => ['id' => $store->id, 'name' => $store->name],
            ],
        ],
    ]);

    $preview = app(TripletexSyncPreviewService::class)->previewZReport($session, $integration, true);

    expect($preview['ok'])->toBeTrue()
        ->and($preview['tripletex_voucher_payload'])->toBeArray()
        ->and($preview['tripletex_voucher_payload']['postings'] ?? [])->not->toBeEmpty()
        ->and($preview['tripletex_postings_display'])->toBeArray()->not->toBeEmpty()
        ->and($preview['tripletex_postings_display'][0])->toHaveKeys(['row', 'account_number', 'account_name', 'amount_gross', 'description'])
        ->and($preview['resolve_error'])->toBeNull();
});

it('returns Z preview via API without resolve_accounts', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
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

    $session = PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => [
            'z_report_data' => [
                'net_amount' => 800,
                'vat_amount' => 200,
                'vat_rate' => 25,
                'total_tips' => 0,
                'net_cash_amount' => 800,
                'net_card_amount' => 0,
                'net_mobile_amount' => 0,
                'net_other_amount' => 0,
                'store' => ['id' => $store->id, 'name' => $store->name],
            ],
        ],
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/tripletex/preview/z-report/'.$session->id)
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure(['lines']);
});

it('queues historical Z-report jobs via API', function () {
    Queue::fake();

    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'sync_enabled' => true,
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

    PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => [
            'z_report_data' => [
                'transactions_count' => 1,
                'net_amount' => 100,
                'vat_rate' => 25,
                'total_tips' => 0,
                'net_cash_amount' => 100,
                'net_card_amount' => 0,
                'net_mobile_amount' => 0,
                'net_other_amount' => 0,
            ],
        ],
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/tripletex/sync/historical', [
        'type' => 'z_report',
        'limit' => 10,
        'only_missing' => true,
    ])
        ->assertOk()
        ->assertJsonPath('queued', 1);

    Queue::assertPushed(SyncTripletexZReportJob::class, 1);
});

it('records a skipped Tripletex Z-report sync run when the Z-report has nothing to post', function () {
    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'sync_enabled' => true,
        'auto_sync_on_z_report' => true,
    ]);

    $session = PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => [
            'z_report_data' => [
                'transactions_count' => 0,
                'net_amount' => 0,
                'net_cash_amount' => 0,
                'net_card_amount' => 0,
                'net_mobile_amount' => 0,
                'net_other_amount' => 0,
            ],
        ],
    ]);

    $sync = app(TripletexZReportSync::class);
    expect($sync->sync($session->id, true))->toBeFalse();

    $run = TripletexSyncRun::query()
        ->where('pos_session_id', $session->id)
        ->where('sync_type', TripletexSyncType::ZReport)
        ->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(TripletexSyncRunStatus::Skipped)
        ->and($run->error_message)->toContain('zero transactions');
});

it('records a skipped Tripletex payout sync run when the payout is not paid', function () {
    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'sync_enabled' => true,
        'auto_sync_payouts' => true,
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_tripletex_skip_unpaid',
        'amount' => 1_000,
        'currency' => 'nok',
        'status' => 'pending',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    $sync = app(TripletexPayoutSync::class);
    expect($sync->sync($payout->id, true))->toBeFalse();

    $run = TripletexSyncRun::query()
        ->where('store_stripe_payout_id', $payout->id)
        ->where('sync_type', TripletexSyncType::Payout)
        ->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(TripletexSyncRunStatus::Skipped)
        ->and($run->error_message)->toContain('not in paid status');
});

it('historical service skips ineligible closed sessions', function () {
    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    TripletexIntegration::factory()->connected()->create(['store_id' => $store->id]);

    PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => [
            'z_report_data' => [
                'transactions_count' => 0,
                'net_amount' => 0,
            ],
        ],
    ]);

    $result = app(TripletexHistoricalSyncService::class)->queueZReports($store, null, null, 10, true);

    expect($result['queued'])->toBe(0)->and($result['skipped'])->toBe(1);
});

it('queues historical payout jobs with skip bank transfer when requested', function () {
    Queue::fake();

    $store = Store::factory()->create();
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'sync_enabled' => true,
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_hist_skip_bank_flag',
        'amount' => 1_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    app(TripletexHistoricalSyncService::class)->queuePayouts($store, null, null, 10, false, true);

    Queue::assertPushed(SyncTripletexPayoutJob::class, function (SyncTripletexPayoutJob $job) use ($payout): bool {
        return $job->storeStripePayoutId === $payout->id
            && $job->force === true
            && $job->skipPayoutBankTransfer === true;
    });
});

it('maps ledger lines to Tripletex voucher JSON with posting_date, vatType, supplier, and header min date', function () {
    $factory = app(TripletexManualVoucherPayloadFactory::class);
    $accountMap = [
        '3000' => ['id' => 1001, 'number' => 3000, 'name' => 'Sales'],
    ];

    $voucher = $factory->build([
        'document_date' => '2026-05-10',
        'currency' => 'NOK',
        'description' => 'Test',
        'lines' => [
            [
                'account' => '3000',
                'debit_minor' => 0,
                'credit_minor' => 100,
                'posting_date' => '2026-05-11',
                'tripletex_vat_type_id' => 44,
                'tripletex_supplier_id' => 9,
                'description' => 'Line',
            ],
        ],
    ], $accountMap);

    expect($voucher['date'])->toBe('2026-05-11')
        ->and($voucher['postings'][0]['date'])->toBe('2026-05-11')
        ->and($voucher['postings'][0]['vatType']['id'])->toBe(44)
        ->and($voucher['postings'][0]['supplier']['id'])->toBe(9)
        ->and($voucher['postings'][0]['amountGross'])->toBe(-1.0)
        ->and($voucher['postings'][0]['amountGrossCurrency'])->toBe(-1.0);
});

it('maps minor units to Tripletex amountGross with two-decimal major units (legacy script parity)', function () {
    $factory = app(TripletexManualVoucherPayloadFactory::class);
    $account = ['id' => 1, 'number' => 3000, 'name' => 'Sales'];
    $accountMap = ['3000' => $account, '1900' => $account];

    $voucher = $factory->build([
        'document_date' => '2026-06-01',
        'currency' => 'NOK',
        'description' => 'Decimal check',
        'lines' => [
            [
                'account' => '3000',
                'debit_minor' => 19_999,
                'credit_minor' => 0,
                'description' => 'Debit 199.99',
            ],
            [
                'account' => '1900',
                'debit_minor' => 0,
                'credit_minor' => 1,
                'description' => 'Credit 0.01',
            ],
            [
                'account' => '3000',
                'debit_minor' => 0,
                'credit_minor' => 12_345,
                'description' => 'Credit 123.45',
            ],
        ],
    ], $accountMap);

    $amounts = collect($voucher['postings'])->pluck('amountGross')->all();

    expect($amounts)->toBe([199.99, -0.01, -123.45]);
});

it('includes payout external ticket sales diagnostics on Tripletex payout preview', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
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

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_diag_preview',
        'amount' => 12_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    $preview = app(TripletexSyncPreviewService::class)->previewPayout($payout, $integration, false);

    expect($preview['ok'])->toBeTrue()
        ->and($preview['mirror_balance_transaction_count'])->toBe(0)
        ->and($preview['payout_external_ticket_sales'])->toBeArray()
        ->and($preview['payout_external_ticket_sales']['enabled'])->toBeFalse()
        ->and($preview['payout_external_ticket_sales']['notes'])->not->toBeEmpty();
});

it('hydrates payout-scoped balance transactions before Tripletex payout preview when sale source rows are missing', function () {
    $store = Store::factory()->create(['stripe_account_id' => 'acct_tripletex_preview_hydrate']);

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'settings' => [
            'ledger' => [
                'payout' => [
                    'credit_account_no' => '1901',
                    'debit_bank_account_no' => '1920',
                ],
            ],
        ],
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_preview_hydrate',
        'amount' => 12_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    $sync = \Mockery::mock(SyncStoreStripeBalanceTransactionsFromStripe::class);
    $sync->shouldReceive('__invoke')
        ->once()
        ->with(\Mockery::on(fn (Store $s): bool => (int) $s->getKey() === (int) $store->getKey()), false, 'po_preview_hydrate')
        ->andReturn(['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => []]);
    app()->instance(SyncStoreStripeBalanceTransactionsFromStripe::class, $sync);

    $preview = app(TripletexSyncPreviewService::class)->previewPayout($payout, $integration, false);

    expect($preview['ok'])->toBeTrue()
        ->and($preview['payout_balance_transaction_sync']['attempted'])->toBeTrue()
        ->and($preview['payout_balance_transaction_sync']['reason'])->toBe('missing_usable_sale_source_rows')
        ->and($preview['payout_balance_transaction_sync']['result']['total'])->toBe(0);
});

it('attributes external ticket diagnostics to metadata rules before zero net amount', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'settings' => [
            'ledger' => [
                'payout' => [
                    'credit_account_no' => '1901',
                    'debit_bank_account_no' => '1920',
                ],
                'external_ticket_sales' => [
                    'enabled' => true,
                    'sales_account_no' => '3200',
                    'require_metadata_keys' => ['booking_id'],
                ],
            ],
        ],
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_diag_filter_order',
        'amount' => 12_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_charge_id' => 'ch_diag_filter_order',
        'pos_session_id' => null,
        'status' => 'succeeded',
        'paid' => true,
        'amount' => 5_000,
        'amount_refunded' => 5_000,
        'metadata' => [],
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_diag_filter_order',
        'type' => 'charge',
        'amount' => 5_000,
        'fee' => 0,
        'net' => 5_000,
        'currency' => 'nok',
        'stripe_charge_id' => 'ch_diag_filter_order',
        'stripe_payout_id' => $payout->stripe_payout_id,
        'stripe_created' => (int) now()->timestamp,
    ]);

    $diagnostics = app(TripletexPayoutLedgerPayloadBuilder::class)
        ->externalTicketSalesDiagnostics($store, $integration, $payout);

    expect($diagnostics['matched_for_voucher_lines'])->toBe(0)
        ->and($diagnostics['skipped_metadata_or_regex'])->toBe(1)
        ->and($diagnostics['skipped_zero_net_amount'])->toBe(0);
});

it('splits Z-report ledger lines by calendar day when enabled and session charges exist', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
                'z_report_split_lines_by_calendar_day' => true,
                'payment_debits' => [
                    'cash' => '1920',
                    'card' => '1921',
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
        'net_amount' => 10_000,
        'vat_amount' => 2_500,
        'vat_rate' => 25,
        'total_tips' => 0,
        'net_cash_amount' => 5_000,
        'net_card_amount' => 5_000,
        'net_mobile_amount' => 0,
        'net_other_amount' => 0,
    ];

    $session = PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closed_at' => Carbon::parse('2026-05-03 23:00:00', 'UTC'),
        'closing_data' => ['z_report_data' => $zReport],
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'paid' => true,
        'amount' => 5_000,
        'amount_refunded' => 0,
        'application_fee_amount' => 0,
        'payment_method' => 'cash',
        'paid_at' => Carbon::parse('2026-05-02 10:00:00', 'UTC'),
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'paid' => true,
        'amount' => 5_000,
        'amount_refunded' => 0,
        'application_fee_amount' => 0,
        'payment_method' => 'card',
        'paid_at' => Carbon::parse('2026-05-03 10:00:00', 'UTC'),
    ]);

    $payload = app(TripletexZReportLedgerPayloadBuilder::class)->build($session, $integration, $zReport);
    $dates = collect($payload['lines'] ?? [])
        ->pluck('posting_date')
        ->filter()
        ->unique()
        ->sort()
        ->values();

    expect($dates->count())->toBeGreaterThanOrEqual(2);
});

it('balances VAT-basis non-cash clearing per posting date when charges use card_present only', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
                'z_report_split_lines_by_calendar_day' => true,
                'payment_debits' => [
                    'cash' => '1920',
                    'card' => '1921',
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
        'net_amount' => 10_000,
        'vat_amount' => 0,
        'vat_rate' => 25,
        'total_tips' => 0,
        'net_cash_amount' => 5_000,
        'net_card_amount' => 5_000,
        'net_mobile_amount' => 0,
        'net_other_amount' => 0,
    ];

    $session = PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closed_at' => Carbon::parse('2026-05-04 12:00:00', 'UTC'),
        'closing_data' => ['z_report_data' => $zReport],
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'paid' => true,
        'amount' => 5_000,
        'amount_refunded' => 0,
        'application_fee_amount' => 0,
        'payment_method' => 'cash',
        'paid_at' => Carbon::parse('2026-05-02 10:00:00', 'UTC'),
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'paid' => true,
        'amount' => 5_000,
        'amount_refunded' => 0,
        'application_fee_amount' => 0,
        'payment_method' => 'card_present',
        'paid_at' => Carbon::parse('2026-05-03 10:00:00', 'UTC'),
    ]);

    $payload = app(TripletexZReportLedgerPayloadBuilder::class)->build($session, $integration, $zReport);
    $lines = $payload['lines'] ?? [];

    $byDate = [];
    foreach ($lines as $line) {
        $d = $line['posting_date'] ?? null;
        if (! is_string($d) || $d === '') {
            continue;
        }
        $byDate[$d] = ($byDate[$d] ?? 0) + (int) ($line['debit_minor'] ?? 0) - (int) ($line['credit_minor'] ?? 0);
    }

    foreach ($byDate as $date => $net) {
        expect($net)->toBe(0, "Postings for {$date} should net to zero (debits = credits)");
    }

    $cardClearingByDate = collect($lines)
        ->filter(fn (array $l): bool => ($l['account'] ?? '') === '1921')
        ->groupBy('posting_date')
        ->map(fn ($group) => $group->sum('debit_minor'))
        ->all();

    expect($cardClearingByDate['2026-05-02'] ?? 0)->toBe(0)
        ->and($cardClearingByDate['2026-05-03'] ?? 0)->toBe(5_000);
});

it('splits payout fees into application fee and Stripe processing fee when mirror fee_details exist', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
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
                'application_fee' => [
                    'debit_account_no' => '2400',
                ],
            ],
        ],
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_fee_split_test',
        'amount' => 50_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_fee_split_1',
        'type' => 'charge',
        'amount' => 60_000,
        'fee' => 500,
        'net' => 59_500,
        'currency' => 'nok',
        'stripe_charge_id' => 'ch_fee_split_1',
        'stripe_payout_id' => $payout->stripe_payout_id,
        'stripe_created' => (int) now()->subDay()->timestamp,
        'fee_details' => [
            ['type' => 'application_fee', 'amount' => 100],
            ['type' => 'stripe_fee', 'amount' => 400],
        ],
    ]);

    $payload = app(TripletexPayoutLedgerPayloadBuilder::class)->build($store, $integration, $payout);
    $kinds = collect($payload['lines'])->pluck('line_kind')->all();

    expect($kinds)->toContain('application_fee_expense')
        ->and($kinds)->toContain('stripe_processing_fee_expense');
});

it('omits clearing-to-bank payout lines when skip payout bank transfer is true', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
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
                'application_fee' => [
                    'debit_account_no' => '2400',
                ],
            ],
        ],
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_skip_bank_lines',
        'amount' => 50_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_skip_bank_lines_1',
        'type' => 'charge',
        'amount' => 60_000,
        'fee' => 500,
        'net' => 59_500,
        'currency' => 'nok',
        'stripe_charge_id' => 'ch_skip_bank_lines_1',
        'stripe_payout_id' => $payout->stripe_payout_id,
        'stripe_created' => (int) now()->subDay()->timestamp,
        'fee_details' => [
            ['type' => 'application_fee', 'amount' => 100],
            ['type' => 'stripe_fee', 'amount' => 400],
        ],
    ]);

    $payload = app(TripletexPayoutLedgerPayloadBuilder::class)->build($store, $integration, $payout, true);
    $kinds = collect($payload['lines'])->pluck('line_kind')->all();

    expect($payload['skip_payout_bank_transfer'] ?? false)->toBeTrue()
        ->and($kinds)->not->toContain('payout_bank')
        ->and($kinds)->not->toContain('payout_clearing')
        ->and($kinds)->toContain('application_fee_expense');
});

it('throws when skip payout bank transfer would leave an empty payout voucher', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
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

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_skip_bank_empty',
        'amount' => 50_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    expect(fn () => app(TripletexPayoutLedgerPayloadBuilder::class)->build($store, $integration, $payout, true))
        ->toThrow(\InvalidArgumentException::class, 'empty');
});

it('adds external ticket lines only for charges without a POS session when enabled', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
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
                'external_ticket_sales' => [
                    'enabled' => true,
                    'sales_account_no' => '3200',
                    'require_metadata_keys' => ['booking_id'],
                ],
            ],
        ],
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_ext_ticket_test',
        'amount' => 20_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    $device = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create(['pos_device_id' => $device->id, 'status' => 'closed']);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_charge_id' => 'ch_ext_pos',
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'paid' => true,
        'amount' => 3_000,
        'metadata' => ['booking_id' => 'b1'],
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_charge_id' => 'ch_ext_web',
        'pos_session_id' => null,
        'status' => 'succeeded',
        'paid' => true,
        'amount' => 5_000,
        'metadata' => ['booking_id' => 'b2'],
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_ext_1',
        'type' => 'charge',
        'amount' => 3_000,
        'fee' => 0,
        'net' => 3_000,
        'currency' => 'nok',
        'stripe_charge_id' => 'ch_ext_pos',
        'stripe_payout_id' => $payout->stripe_payout_id,
        'stripe_created' => (int) now()->timestamp,
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_ext_2',
        'type' => 'charge',
        'amount' => 5_000,
        'fee' => 0,
        'net' => 5_000,
        'currency' => 'nok',
        'stripe_charge_id' => 'ch_ext_web',
        'stripe_payout_id' => $payout->stripe_payout_id,
        'stripe_created' => (int) now()->timestamp,
    ]);

    $payload = app(TripletexPayoutLedgerPayloadBuilder::class)->build($store, $integration, $payout);
    $externalCredits = collect($payload['lines'] ?? [])
        ->where('line_kind', 'external_ticket_sales')
        ->sum('credit_minor');

    expect($externalCredits)->toBe(5_000);
});

it('combines external ticket payout lines per calendar day', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
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
                'external_ticket_sales' => [
                    'enabled' => true,
                    'sales_account_no' => '3200',
                ],
            ],
        ],
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_ext_ticket_by_day',
        'amount' => 20_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    foreach ([
        ['id' => 'ch_ext_web_day_1a', 'amount' => 5_000, 'created' => Carbon::parse('2026-05-11 09:00:00', config('app.timezone'))->timestamp],
        ['id' => 'ch_ext_web_day_1b', 'amount' => 7_000, 'created' => Carbon::parse('2026-05-11 14:00:00', config('app.timezone'))->timestamp],
        ['id' => 'ch_ext_web_day_2a', 'amount' => 3_000, 'created' => Carbon::parse('2026-05-12 10:00:00', config('app.timezone'))->timestamp],
    ] as $row) {
        ConnectedCharge::factory()->create([
            'stripe_account_id' => $store->stripe_account_id,
            'stripe_charge_id' => $row['id'],
            'pos_session_id' => null,
            'status' => 'succeeded',
            'paid' => true,
            'amount' => $row['amount'],
            'metadata' => ['booking_id' => $row['id']],
        ]);

        StoreStripeBalanceTransaction::query()->create([
            'store_id' => $store->id,
            'stripe_account_id' => (string) $store->stripe_account_id,
            'stripe_balance_transaction_id' => 'txn_'.$row['id'],
            'type' => 'charge',
            'amount' => $row['amount'],
            'fee' => 0,
            'net' => $row['amount'],
            'currency' => 'nok',
            'stripe_charge_id' => $row['id'],
            'stripe_payout_id' => $payout->stripe_payout_id,
            'stripe_created' => $row['created'],
        ]);
    }

    $payload = app(TripletexPayoutLedgerPayloadBuilder::class)->build($store, $integration, $payout);
    $externalSales = collect($payload['lines'] ?? [])
        ->where('line_kind', 'external_ticket_sales')
        ->values();
    $externalClearing = collect($payload['lines'] ?? [])
        ->where('line_kind', 'external_ticket_clearing')
        ->values();

    expect($externalSales)->toHaveCount(2)
        ->and($externalClearing)->toHaveCount(2)
        ->and($externalSales->pluck('posting_date')->all())->toBe(['2026-05-11', '2026-05-12'])
        ->and($externalSales->pluck('credit_minor')->all())->toBe([12_000, 3_000])
        ->and($externalClearing->pluck('debit_minor')->all())->toBe([12_000, 3_000]);
});

it('matches external ticket sales with eventKey when require_metadata_keys is not configured', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
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
                'external_ticket_sales' => [
                    'enabled' => true,
                    'sales_account_no' => '3200',
                ],
            ],
        ],
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_ext_ticket_eventkey',
        'amount' => 50_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_charge_id' => 'ch_ext_web_eventkey',
        'pos_session_id' => null,
        'status' => 'succeeded',
        'paid' => true,
        'amount' => 13_800,
        'metadata' => [
            'eventKey' => '534cbf13-7309-4e9b-bd57-8226cbab5846',
            'seats' => '7-8,7-7',
        ],
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_ext_eventkey',
        'type' => 'charge',
        'amount' => 13_800,
        'fee' => 0,
        'net' => 13_800,
        'currency' => 'nok',
        'stripe_charge_id' => 'ch_ext_web_eventkey',
        'stripe_payout_id' => $payout->stripe_payout_id,
        'stripe_created' => (int) now()->timestamp,
    ]);

    $payload = app(TripletexPayoutLedgerPayloadBuilder::class)->build($store, $integration, $payout);
    $externalCredits = collect($payload['lines'] ?? [])
        ->where('line_kind', 'external_ticket_sales')
        ->sum('credit_minor');

    expect($externalCredits)->toBe(13_800);
});

it('matches external ticket sales with event_key when require_metadata_keys is not configured', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
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
                'external_ticket_sales' => [
                    'enabled' => true,
                    'sales_account_no' => '3200',
                ],
            ],
        ],
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_ext_ticket_event_snake',
        'amount' => 50_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_charge_id' => 'ch_ext_web_event_snake',
        'pos_session_id' => null,
        'status' => 'succeeded',
        'paid' => true,
        'amount' => 9_900,
        'metadata' => [
            'event_key' => '534cbf13-7309-4e9b-bd57-8226cbab5846',
            'seats' => 'A1',
        ],
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_ext_event_snake',
        'type' => 'charge',
        'amount' => 9_900,
        'fee' => 0,
        'net' => 9_900,
        'currency' => 'nok',
        'stripe_charge_id' => 'ch_ext_web_event_snake',
        'stripe_payout_id' => $payout->stripe_payout_id,
        'stripe_created' => (int) now()->timestamp,
    ]);

    $payload = app(TripletexPayoutLedgerPayloadBuilder::class)->build($store, $integration, $payout);
    $externalCredits = collect($payload['lines'] ?? [])
        ->where('line_kind', 'external_ticket_sales')
        ->sum('credit_minor');

    expect($externalCredits)->toBe(9_900);
});

it('adds external ticket lines for Stripe balance transaction type payment with py_ id (Klarna-style)', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
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
                'external_ticket_sales' => [
                    'enabled' => true,
                    'sales_account_no' => '3200',
                ],
            ],
        ],
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_ext_ticket_py_klarna',
        'amount' => 200_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_charge_id' => 'py_ext_klarna_web_1',
        'pos_session_id' => null,
        'status' => 'succeeded',
        'paid' => true,
        'amount' => 142_800,
        'metadata' => [
            'booking_id' => '7056',
            'event_key' => 'finnsnes-heimlymyra-1905-1800-1777980729',
        ],
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_ext_py_klarna',
        'type' => 'payment',
        'amount' => 142_800,
        'fee' => 3_360,
        'net' => 139_440,
        'currency' => 'nok',
        'stripe_charge_id' => 'py_ext_klarna_web_1',
        'stripe_payout_id' => $payout->stripe_payout_id,
        'stripe_created' => (int) now()->timestamp,
        'source_metadata' => [
            'booking_id' => '7056',
            'event_key' => 'finnsnes-heimlymyra-1905-1800-1777980729',
        ],
    ]);

    $payload = app(TripletexPayoutLedgerPayloadBuilder::class)->build($store, $integration, $payout);
    $externalCredits = collect($payload['lines'] ?? [])
        ->where('line_kind', 'external_ticket_sales')
        ->sum('credit_minor');

    expect($externalCredits)->toBe(142_800);
});

it('does not match kiosk-style charges without default ticket metadata keys under default metadata rule', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
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
                'external_ticket_sales' => [
                    'enabled' => true,
                    'sales_account_no' => '3200',
                ],
            ],
        ],
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_ext_ticket_kiosk',
        'amount' => 10_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_charge_id' => 'ch_ext_kiosk',
        'pos_session_id' => null,
        'status' => 'succeeded',
        'paid' => true,
        'amount' => 5_900,
        'description' => 'Kioskvarer',
        'metadata' => [],
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_ext_kiosk',
        'type' => 'charge',
        'amount' => 5_900,
        'fee' => 0,
        'net' => 5_900,
        'currency' => 'nok',
        'stripe_charge_id' => 'ch_ext_kiosk',
        'stripe_payout_id' => $payout->stripe_payout_id,
        'stripe_created' => (int) now()->timestamp,
    ]);

    $payload = app(TripletexPayoutLedgerPayloadBuilder::class)->build($store, $integration, $payout);
    $externalCredits = collect($payload['lines'] ?? [])
        ->where('line_kind', 'external_ticket_sales')
        ->sum('credit_minor');

    expect($externalCredits)->toBe(0);
});

it('matches external ticket metadata when booking_id is only on balance transaction source_metadata', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
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
                'external_ticket_sales' => [
                    'enabled' => true,
                    'sales_account_no' => '3200',
                    'require_metadata_keys' => ['booking_id'],
                ],
            ],
        ],
    ]);

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_ext_ticket_meta_merge',
        'amount' => 5_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_charge_id' => 'ch_ext_web_meta_split',
        'pos_session_id' => null,
        'status' => 'succeeded',
        'paid' => true,
        'amount' => 5_000,
        'metadata' => ['pos_enrichment_only' => '1'],
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_ext_meta_merge',
        'type' => 'charge',
        'amount' => 5_000,
        'fee' => 0,
        'net' => 5_000,
        'currency' => 'nok',
        'stripe_charge_id' => 'ch_ext_web_meta_split',
        'stripe_payout_id' => $payout->stripe_payout_id,
        'stripe_created' => (int) now()->timestamp,
        'source_metadata' => ['booking_id' => 'b-merge-1'],
    ]);

    $payload = app(TripletexPayoutLedgerPayloadBuilder::class)->build($store, $integration, $payout);
    $externalCredits = collect($payload['lines'] ?? [])
        ->where('line_kind', 'external_ticket_sales')
        ->sum('credit_minor');

    expect($externalCredits)->toBe(5_000);
});

it('reconciles a successful payout sync payload against mirror totals', function () {
    $store = Store::factory()->create();

    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
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

    $payout = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_payout_id' => 'po_recon_ok',
        'amount' => 10_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => now(),
        'automatic' => true,
    ]));

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => (string) $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_recon_1',
        'type' => 'charge',
        'amount' => 12_000,
        'fee' => 200,
        'net' => 11_800,
        'currency' => 'nok',
        'stripe_charge_id' => 'ch_recon_1',
        'stripe_payout_id' => $payout->stripe_payout_id,
        'stripe_created' => (int) now()->timestamp,
    ]);

    $payload = app(TripletexPayoutLedgerPayloadBuilder::class)->build($store, $integration, $payout);

    TripletexSyncRun::factory()->create([
        'tripletex_integration_id' => $integration->id,
        'store_id' => $store->id,
        'sync_type' => TripletexSyncType::Payout,
        'pos_session_id' => null,
        'store_stripe_payout_id' => $payout->id,
        'status' => TripletexSyncRunStatus::Success,
        'request_payload' => $payload,
        'idempotency_key' => 'tripletex:payout:test-recon-'.uniqid(),
    ]);

    $report = app(TripletexPayoutReconciliationService::class)->reconcile($payout, $integration);

    expect($report['status'])->toBe('ok')
        ->and($report['messages'])->toBe([]);
});
