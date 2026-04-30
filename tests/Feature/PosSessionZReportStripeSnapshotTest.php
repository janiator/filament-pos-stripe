<?php

use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\ConnectedCharge;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\StoreStripeBalanceTransaction;
use App\Models\StoreStripePayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('embeds stripe_fees_minor and payout_to_bank_minor when generating a fresh Z-report for a closed session', function () {
    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_z_snapshot_fresh',
    ]);
    $user = User::factory()->create();
    $device = PosDevice::factory()->create(['store_id' => $store->id]);
    $closedAt = now()->startOfDay()->addHours(16);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $device->id,
        'user_id' => $user->id,
        'status' => 'closed',
        'opened_at' => $closedAt->copy()->subHour(),
        'closed_at' => $closedAt,
        'closing_data' => [],
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'stripe_charge_id' => 'ch_z_snapshot_fresh',
        'status' => 'succeeded',
        'payment_method' => 'card',
        'amount' => 5_000,
        'paid' => true,
        'paid_at' => $closedAt->copy()->subMinutes(30),
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_z_snapshot_fresh',
        'type' => 'charge',
        'amount' => 5_000,
        'fee' => 180,
        'net' => 4_820,
        'currency' => 'nok',
        'status' => 'available',
        'stripe_charge_id' => 'ch_z_snapshot_fresh',
        'stripe_created' => (int) $closedAt->copy()->subHour()->timestamp,
    ]);

    StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_payout_id' => 'po_z_snapshot_fresh',
        'amount' => 12_000,
        'currency' => 'nok',
        'status' => 'paid',
        'arrival_date' => $closedAt->copy()->startOfDay(),
        'stripe_created' => (int) $closedAt->copy()->subDay()->timestamp,
    ]);

    $session->refresh()->load(['charges', 'store', 'posDevice', 'user', 'receipts']);

    $report = PosSessionsTable::generateZReport($session, attachMissingData: false);

    expect($report['stripe_fees_minor'])->toBe(180)
        ->and($report['payout_to_bank_minor'])->toBe(12_000);

    $session->refresh();
    expect($session->closing_data['z_report_data']['stripe_fees_minor'] ?? null)->toBe(180)
        ->and($session->closing_data['z_report_data']['payout_to_bank_minor'] ?? null)->toBe(12_000);
});

it('preserves positive stripe_fees_minor on cached Z-report when merging settlement totals', function () {
    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_z_snapshot_cached_override',
    ]);
    $user = User::factory()->create();
    $device = PosDevice::factory()->create(['store_id' => $store->id]);
    $closedAt = now()->subDay();

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $device->id,
        'user_id' => $user->id,
        'status' => 'closed',
        'opened_at' => $closedAt->copy()->subHour(),
        'closed_at' => $closedAt,
        'closing_data' => [
            'z_report_data' => [
                'total_refunded' => 0,
                'stripe_fees_minor' => 777,
                'report_type' => 'Z-Report',
            ],
        ],
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'stripe_charge_id' => 'ch_z_snapshot_override',
        'status' => 'succeeded',
        'payment_method' => 'card',
        'amount' => 1_000,
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_z_snapshot_override',
        'type' => 'charge',
        'amount' => 1_000,
        'fee' => 99,
        'net' => 901,
        'currency' => 'nok',
        'status' => 'available',
        'stripe_charge_id' => 'ch_z_snapshot_override',
        'stripe_created' => (int) now()->subHour()->timestamp,
    ]);

    $session->refresh()->load(['charges']);

    $report = PosSessionsTable::generateZReport($session, attachMissingData: false);

    expect($report['stripe_fees_minor'])->toBe(777);
});

it('backfills missing stripe settlement keys on cached Z-report and persists', function () {
    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_z_snapshot_cached_backfill',
    ]);
    $user = User::factory()->create();
    $device = PosDevice::factory()->create(['store_id' => $store->id]);
    $closedAt = now()->subHours(2);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $device->id,
        'user_id' => $user->id,
        'status' => 'closed',
        'opened_at' => $closedAt->copy()->subHour(),
        'closed_at' => $closedAt,
        'closing_data' => [
            'z_report_data' => [
                'total_refunded' => 0,
                'report_type' => 'Z-Report',
            ],
        ],
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'stripe_charge_id' => 'ch_z_snapshot_backfill',
        'status' => 'succeeded',
        'payment_method' => 'card',
        'amount' => 2_000,
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_z_snapshot_backfill',
        'type' => 'charge',
        'amount' => 2_000,
        'fee' => 220,
        'net' => 1_780,
        'currency' => 'nok',
        'status' => 'available',
        'stripe_charge_id' => 'ch_z_snapshot_backfill',
        'stripe_created' => (int) now()->subHour()->timestamp,
    ]);

    $session->refresh()->load(['charges']);

    PosSessionsTable::generateZReport($session, attachMissingData: false);

    $session->refresh();
    expect($session->closing_data['z_report_data']['stripe_fees_minor'] ?? null)->toBe(220)
        ->and((int) ($session->closing_data['z_report_data']['payout_to_bank_minor'] ?? 0))->toBe(0);
});

it('includes stripe fee labels in z-report embed view used for pdf', function () {
    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_z_embed_view',
    ]);
    $user = User::factory()->create();
    $device = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $device->id,
        'user_id' => $user->id,
        'status' => 'closed',
        'opened_at' => now()->subHour(),
        'closed_at' => now(),
        'closing_data' => [],
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'stripe_charge_id' => 'ch_z_embed_view',
        'status' => 'succeeded',
        'payment_method' => 'card',
        'amount' => 3_000,
    ]);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_z_embed_view',
        'type' => 'charge',
        'amount' => 3_000,
        'fee' => 90,
        'net' => 2_910,
        'currency' => 'nok',
        'status' => 'available',
        'stripe_charge_id' => 'ch_z_embed_view',
        'stripe_created' => (int) now()->subHour()->timestamp,
    ]);

    $session->refresh()->load(['charges', 'store', 'posDevice', 'user', 'receipts']);
    $report = PosSessionsTable::generateZReport($session, attachMissingData: false);

    $html = view('reports.embed.z-report', [
        'session' => $session,
        'report' => $report,
    ])->render();

    expect($html)
        ->toContain('Stripe-gebyr')
        ->toContain('Utbetaling til bank')
        ->toContain('0.90')
        ->toContain('NOK');
});
