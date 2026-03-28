<?php

use App\Enums\PowerOfficeMappingBasis;
use App\Models\Collection as ProductCollection;
use App\Models\ConnectedProduct;
use App\Models\PosSession;
use App\Models\PowerOfficeAccountMapping;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Services\PowerOffice\PowerOfficeLedgerPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('aggregates products sold by primary collection id', function () {
    $store = Store::factory()->create();
    $col = ProductCollection::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'Parts',
        'handle' => 'parts',
        'active' => true,
        'sort_order' => 0,
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
    ]);
    $product->collections()->attach($col->id);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Category,
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'store_id' => $store->id,
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Category,
        'basis_key' => (string) $col->id,
        'sales_account_no' => '3100',
        'cash_account_no' => '1920',
        'card_clearing_account_no' => '1921',
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    $zReport = [
        'net_amount' => 10_000,
        'vat_amount' => 2_000,
        'vat_rate' => 25,
        'total_tips' => 0,
        'products_sold' => [
            ['product_id' => $product->id, 'amount' => 10_000],
        ],
        'net_cash_amount' => 10_000,
        'net_card_amount' => 0,
        'net_mobile_amount' => 0,
        'net_other_amount' => 0,
        'by_payment_method_net' => [
            'cash' => ['amount' => 10_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    $salesLine = collect($payload['lines'])->first(fn (array $l): bool => $l['credit_minor'] === 10_000 && str_contains($l['description'], 'sales'));
    expect($salesLine)->not->toBeNull()
        ->and($salesLine['account'])->toBe('3100');
});

it('uses default sales account when collection mapping is missing', function () {
    $store = Store::factory()->create();
    $col = ProductCollection::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'New',
        'handle' => 'new',
        'active' => true,
        'sort_order' => 0,
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
    ]);
    $product->collections()->attach($col->id);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Category,
        'settings' => [
            'ledger' => [
                'default_sales_account_no' => '3999',
                'payment_debits' => [
                    'cash' => '1920',
                ],
            ],
        ],
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'store_id' => $store->id,
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Category,
        'basis_key' => '99999',
        'sales_account_no' => '3000',
        'cash_account_no' => '1920',
        'card_clearing_account_no' => '1921',
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    $zReport = [
        'net_amount' => 5_000,
        'vat_amount' => 1_000,
        'vat_rate' => 25,
        'total_tips' => 0,
        'products_sold' => [
            ['product_id' => $product->id, 'amount' => 5_000],
        ],
        'net_cash_amount' => 5_000,
        'net_card_amount' => 0,
        'net_mobile_amount' => 0,
        'net_other_amount' => 0,
        'by_payment_method_net' => [
            'cash' => ['amount' => 5_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    $salesLine = collect($payload['lines'])->first(fn (array $l): bool => $l['credit_minor'] === 5_000 && str_contains($l['description'], 'sales'));
    expect($salesLine)->not->toBeNull()
        ->and($salesLine['account'])->toBe('3999');
});
