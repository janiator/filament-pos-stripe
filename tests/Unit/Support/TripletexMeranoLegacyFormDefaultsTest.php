<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

use App\Models\Store;
use App\Models\TripletexAccountMapping;
use App\Models\TripletexIntegration;
use App\Support\Tripletex\TripletexMeranoLegacyFormDefaults;

it('prefills when integration has no mappings and no payout routing', function () {
    $store = Store::factory()->create();
    $integration = TripletexIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'settings' => null,
    ]);

    expect(TripletexMeranoLegacyFormDefaults::shouldPrefill($integration))->toBeTrue();

    $base = [
        'vat_sales_25' => '',
        'vat_sales_15' => '',
        'vat_sales_0' => '',
        'ledger_payout_credit_account_no' => '',
        'ledger_payout_debit_bank_account_no' => '',
        'ledger_fee_debit_account_no' => '',
        'ledger_existing' => '9999',
    ];

    $merged = TripletexMeranoLegacyFormDefaults::mergeWhenPristine($base, $integration);

    expect($merged['vat_sales_25'])->toBe('3001')
        ->and($merged['vat_sales_15'])->toBe('3200')
        ->and($merged['vat_sales_0'])->toBe('3201')
        ->and($merged['ledger_payout_credit_account_no'])->toBe('1901')
        ->and($merged['ledger_payout_debit_bank_account_no'])->toBe('1920')
        ->and($merged['ledger_fee_debit_account_no'])->toBe('7771')
        ->and($merged['ledger_existing'])->toBe('9999');
});

it('does not prefill when account mappings exist', function () {
    $store = Store::factory()->create();
    $integration = TripletexIntegration::factory()->connected()->create(['store_id' => $store->id]);

    TripletexAccountMapping::factory()->create([
        'store_id' => $store->id,
        'tripletex_integration_id' => $integration->getKey(),
    ]);

    expect(TripletexMeranoLegacyFormDefaults::shouldPrefill($integration))->toBeFalse();

    $base = ['vat_sales_25' => ''];
    $merged = TripletexMeranoLegacyFormDefaults::mergeWhenPristine($base, $integration);

    expect($merged['vat_sales_25'])->toBe('');
});

it('does not prefill when payout routing is already stored', function () {
    $store = Store::factory()->create();
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

    expect(TripletexMeranoLegacyFormDefaults::shouldPrefill($integration))->toBeFalse();
});
