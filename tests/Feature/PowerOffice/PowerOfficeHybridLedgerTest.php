<?php

use App\Enums\PowerOfficeMappingBasis;
use App\Exceptions\PowerOffice\MissingPowerOfficeMappingException;
use App\Models\ArticleGroupCode;
use App\Models\Collection as ProductCollection;
use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PosSession;
use App\Models\PowerOfficeAccountMapping;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Models\Vendor;
use App\Services\PowerOffice\PowerOfficeLedgerPayloadBuilder;
use App\Support\PowerOffice\PowerOfficeLedgerSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('posts collection sales to mapped revenue accounts for hybrid category split', function () {
    $store = Store::factory()->create();
    $col = ProductCollection::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'Neverstua',
        'handle' => 'neverstua',
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
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
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
        'by_payment_method_net' => [
            'cash' => ['amount' => 10_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    $salesLine = collect($payload['lines'])->first(fn (array $l): bool => $l['account'] === '3000' && $l['credit_minor'] === 10_000);
    expect($salesLine)->not->toBeNull();
});

it('splits stuttreist vendor sales between reskontro and commission revenue account', function () {
    $store = Store::factory()->create();
    $vendor = Vendor::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'Stuttreist',
        'active' => true,
        'commission_percent' => 10,
        'supplier_ledger_account_number' => '40001',
        'commission_revenue_account_number' => '3023',
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'vendor_id' => $vendor->id,
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Category,
        'settings' => [
            'ledger' => [
                'payment_debits' => ['cash' => '1920'],
            ],
        ],
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'store_id' => $store->id,
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Category,
        'basis_key' => '999',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
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
        'by_payment_method_net' => [
            'cash' => ['amount' => 10_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    expect(collect($payload['lines'])->firstWhere('account', '40001')['credit_minor'] ?? null)->toBe(9_000)
        ->and(collect($payload['lines'])->firstWhere('account', '3023')['credit_minor'] ?? null)->toBe(1_000);
});

it('posts vipps fees to configured fee account and reduces vipps clearing debit', function () {
    $store = Store::factory()->create();
    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
                'payment_debits' => ['vipps' => '1925'],
                'payment_method_fees' => [
                    'vipps' => ['debit_account_no' => '7720'],
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
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'payment_method' => 'vipps',
        'amount' => 5_000,
        'application_fee_amount' => 125,
    ]);

    $zReport = [
        'net_amount' => 5_000,
        'vat_amount' => 1_000,
        'vat_rate' => 25,
        'total_tips' => 0,
        'sales_net_minor_by_vat_rate' => ['25' => 4_000],
        'vat_minor_by_vat_rate' => ['25' => 1_000],
        'by_payment_method_net' => [
            'vipps' => ['amount' => 5_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    expect(collect($payload['lines'])->firstWhere('account', '7720')['debit_minor'] ?? null)->toBe(125)
        ->and(collect($payload['lines'])->firstWhere('account', '1925')['debit_minor'] ?? null)->toBe(4_875);
});

it('reduces fallback mobile clearing debit when vipps fee lines are posted without payment method net data', function () {
    $store = Store::factory()->create();
    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
                'payment_method_fees' => [
                    'vipps' => ['debit_account_no' => '7720'],
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
        'card_clearing_account_no' => '1925',
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'payment_method' => 'vipps',
        'amount' => 5_000,
        'application_fee_amount' => 125,
    ]);

    $zReport = [
        'net_amount' => 5_000,
        'vat_amount' => 1_000,
        'vat_rate' => 25,
        'total_tips' => 0,
        'sales_net_minor_by_vat_rate' => ['25' => 4_000],
        'vat_minor_by_vat_rate' => ['25' => 1_000],
        'net_cash_amount' => 0,
        'net_card_amount' => 0,
        'net_mobile_amount' => 5_000,
        'net_other_amount' => 0,
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    expect(collect($payload['lines'])->firstWhere('account', '7720')['debit_minor'] ?? null)->toBe(125)
        ->and(collect($payload['lines'])->where('account', '1925')->sum('debit_minor'))->toBe(4_875);
});

it('subtracts vipps fees once when multiple payment method net keys match vipps', function () {
    $store = Store::factory()->create();
    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
                'payment_debits' => [
                    'vipps' => '1925',
                    'custom_vipps' => '1925',
                ],
                'payment_method_fees' => [
                    'vipps' => ['debit_account_no' => '7720'],
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
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'payment_method' => 'vipps',
        'amount' => 5_000,
        'application_fee_amount' => 125,
    ]);

    $zReport = [
        'net_amount' => 5_000,
        'vat_amount' => 1_000,
        'vat_rate' => 25,
        'total_tips' => 0,
        'sales_net_minor_by_vat_rate' => ['25' => 4_000],
        'vat_minor_by_vat_rate' => ['25' => 1_000],
        'by_payment_method_net' => [
            'vipps' => ['amount' => 3_000, 'count' => 1, 'tips' => 0],
            'custom_vipps' => ['amount' => 2_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    expect(collect($payload['lines'])->firstWhere('account', '7720')['debit_minor'] ?? null)->toBe(125)
        ->and(collect($payload['lines'])->where('account', '1925')->sum('debit_minor'))->toBe(4_875);
});

it('posts gross sales with no separate VAT line and applies department to all lines', function () {
    $store = Store::factory()->create();
    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
                'department_no' => '20',
                'payment_debits' => ['cash' => '1920'],
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
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    $zReport = [
        'net_amount' => 1_000,
        'vat_amount' => 250,
        'vat_rate' => 25,
        'total_tips' => 0,
        'by_payment_method_net' => [
            'cash' => ['amount' => 1_250, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    // Gross sales credit with the account's vat code; PowerOffice splits out the VAT itself.
    expect($payload['department_no'])->toBe('20')
        ->and(collect($payload['lines'])->firstWhere('account', '3000')['credit_minor'] ?? null)->toBe(1_250)
        ->and(collect($payload['lines'])->firstWhere('account', '2700'))->toBeNull()
        ->and(collect($payload['lines'])->firstWhere('account', '1920')['debit_minor'] ?? null)->toBe(1_250);

    $apiBody = app(\App\Services\PowerOffice\PowerOfficeManualVoucherPayloadFactory::class)->build(
        array_merge($payload, ['department_id' => 20]),
        [
            '3000' => ['id' => 1, 'vat_code_id' => 3],
            '1920' => ['id' => 2, 'vat_code_id' => null],
        ],
        'poweroffice_z_report_test',
        99,
    );

    foreach ($apiBody['VoucherLines'] as $line) {
        expect($line['DepartmentId'] ?? null)->toBe(20)
            ->and($line)->toHaveKey('VatId');
    }

    $salesLine = collect($apiBody['VoucherLines'])->firstWhere('AccountId', 1);
    $cashLine = collect($apiBody['VoucherLines'])->firstWhere('AccountId', 2);
    expect($salesLine['VatId'])->toBe(3)
        ->and($cashLine['VatId'])->toBe(99);
});

it('uses vendor supplier account for vendor basis without commission', function () {
    $store = Store::factory()->create();
    $vendor = Vendor::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'Neverstua vendor',
        'active' => true,
        'supplier_ledger_account_number' => '3000',
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vendor,
        'settings' => [
            'ledger' => [
                'payment_debits' => ['cash' => '1920'],
            ],
        ],
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'store_id' => $store->id,
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Vendor,
        'basis_key' => PowerOfficeLedgerSettings::SHARED_MAPPING_BASIS_KEY,
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    $zReport = [
        'net_amount' => 2_000,
        'vat_amount' => 400,
        'vat_rate' => 25,
        'total_tips' => 0,
        'sales_by_vendor' => [
            [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'amount' => 2_000,
                'commission_percent' => null,
                'commission_amount' => 0,
            ],
        ],
        'by_payment_method_net' => [
            'cash' => ['amount' => 2_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    expect(collect($payload['lines'])->firstWhere('account', '3000')['credit_minor'] ?? null)->toBe(2_000);
});

it('prefers article group mapping over product collection for hybrid sales', function () {
    $store = Store::factory()->create();
    ArticleGroupCode::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'code' => '04001',
        'name' => 'Neverstua',
        'active' => true,
        'sort_order' => 0,
    ]);
    $col = ProductCollection::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'Vaskeri',
        'handle' => 'vaskeri',
        'active' => true,
        'sort_order' => 0,
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'article_group_code' => '04001',
    ]);
    $product->collections()->attach($col->id);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Category,
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'store_id' => $store->id,
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::ArticleGroup,
        'basis_key' => '04001',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
    ]);
    PowerOfficeAccountMapping::factory()->create([
        'store_id' => $store->id,
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Category,
        'basis_key' => (string) $col->id,
        'sales_account_no' => '3020',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
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
        'by_payment_method_net' => [
            'cash' => ['amount' => 5_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    expect(collect($payload['lines'])->firstWhere('account', '3000')['credit_minor'] ?? null)->toBe(5_000)
        ->and(collect($payload['lines'])->firstWhere('account', '3020'))->toBeNull();
});

it('falls back to product collection when article group is unmapped', function () {
    $store = Store::factory()->create();
    ArticleGroupCode::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'code' => '04002',
        'name' => 'Uteavdelingen',
        'active' => true,
        'sort_order' => 0,
    ]);
    $col = ProductCollection::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'Uteavdelingen',
        'handle' => 'ute',
        'active' => true,
        'sort_order' => 0,
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'article_group_code' => '04002',
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
        'sales_account_no' => '3022',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    $zReport = [
        'net_amount' => 4_000,
        'vat_amount' => 800,
        'vat_rate' => 25,
        'total_tips' => 0,
        'products_sold' => [
            ['product_id' => $product->id, 'amount' => 4_000],
        ],
        'by_payment_method_net' => [
            'cash' => ['amount' => 4_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    expect(collect($payload['lines'])->firstWhere('account', '3022')['credit_minor'] ?? null)->toBe(4_000);
});

it('uses vendor commission split instead of article group or collection mapping', function () {
    $store = Store::factory()->create();
    ArticleGroupCode::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'code' => '04003',
        'name' => 'Stuttreist',
        'active' => true,
        'sort_order' => 0,
    ]);
    $vendor = Vendor::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'Stuttreist',
        'active' => true,
        'commission_percent' => 10,
        'supplier_ledger_account_number' => '40001',
        'commission_revenue_account_number' => '3023',
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'article_group_code' => '04003',
        'vendor_id' => $vendor->id,
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Category,
        'settings' => [
            'ledger' => [
                'payment_debits' => ['cash' => '1920'],
            ],
        ],
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'store_id' => $store->id,
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::ArticleGroup,
        'basis_key' => '04003',
        'sales_account_no' => '3023',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
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
        'by_payment_method_net' => [
            'cash' => ['amount' => 10_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    expect(collect($payload['lines'])->firstWhere('account', '40001')['credit_minor'] ?? null)->toBe(9_000)
        ->and(collect($payload['lines'])->firstWhere('account', '3023')['credit_minor'] ?? null)->toBe(1_000);
});

it('backfills missing products sold before applying hybrid vendor commission splits', function () {
    $store = Store::factory()->create();
    $vendor = Vendor::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'Stuttreist',
        'active' => true,
        'commission_percent' => 10,
        'supplier_ledger_account_number' => '40001',
        'commission_revenue_account_number' => '3023',
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'vendor_id' => $vendor->id,
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Category,
        'settings' => [
            'ledger' => [
                'payment_debits' => ['cash' => '1920'],
            ],
        ],
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'store_id' => $store->id,
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Category,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'payment_method' => 'cash',
        'amount' => 10_000,
        'metadata' => [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10_000],
            ],
        ],
    ]);

    $zReport = [
        'net_amount' => 10_000,
        'vat_amount' => 2_000,
        'vat_rate' => 25,
        'total_tips' => 0,
        'by_payment_method_net' => [
            'cash' => ['amount' => 10_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    expect(collect($payload['lines'])->firstWhere('account', '40001')['credit_minor'] ?? null)->toBe(9_000)
        ->and(collect($payload['lines'])->firstWhere('account', '3023')['credit_minor'] ?? null)->toBe(1_000)
        ->and(collect($payload['lines'])->firstWhere('account', '3000'))->toBeNull();
});

it('rejects commission vendor sales when the vendor share account cannot be resolved', function () {
    $store = Store::factory()->create();
    $vendor = Vendor::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'Stuttreist',
        'active' => true,
        'commission_percent' => 10,
        'commission_revenue_account_number' => '3023',
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'vendor_id' => $vendor->id,
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Category,
        'settings' => [
            'ledger' => [
                'payment_debits' => ['cash' => '1920'],
                'commission_revenue_account_no' => '3023',
            ],
        ],
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'store_id' => $store->id,
        'power_office_integration_id' => $integration->id,
        'basis_type' => PowerOfficeMappingBasis::Category,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'payment_method' => 'cash',
        'amount' => 10_000,
        'metadata' => [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10_000],
            ],
        ],
    ]);

    $zReport = [
        'net_amount' => 10_000,
        'vat_amount' => 2_000,
        'vat_rate' => 25,
        'total_tips' => 0,
        'by_payment_method_net' => [
            'cash' => ['amount' => 10_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);
})->throws(MissingPowerOfficeMappingException::class);
