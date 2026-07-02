<?php

use App\Enums\PowerOfficeMappingBasis;
use App\Models\ArticleGroupCode;
use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PosSession;
use App\Models\PowerOfficeAccountMapping;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Models\Vendor;
use App\Services\PowerOffice\PowerOfficeLedgerPayloadBuilder;
use App\Services\PowerOffice\PowerOfficeSyncPreviewService;

it('uses accounting-scoped products sold and reconciles hybrid sales to z-report net amount', function () {
    $store = Store::factory()->create();
    ArticleGroupCode::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'code' => '04010',
        'name' => 'Vaskeri',
        'active' => true,
        'sort_order' => 0,
    ]);
    $vendor = Vendor::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'Stuttreist vendor',
        'active' => true,
        'commission_percent' => 10,
        'supplier_ledger_account_number' => '40001',
        'commission_revenue_account_number' => '3023',
    ]);
    $vaskeriProduct = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'article_group_code' => '04010',
    ]);
    $vendorProduct = ConnectedProduct::factory()->create([
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
        'basis_type' => PowerOfficeMappingBasis::ArticleGroup,
        'basis_key' => '04010',
        'sales_account_no' => '3020',
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
                ['product_id' => $vaskeriProduct->id, 'quantity' => 1, 'unit_price' => 7_000],
                ['product_id' => $vendorProduct->id, 'quantity' => 1, 'unit_price' => 3_000],
            ],
        ],
    ]);

    $zReport = [
        'net_amount' => 10_000,
        'vat_amount' => 2_000,
        'vat_rate' => 25,
        'total_tips' => 0,
        'products_sold' => [
            ['product_id' => $vaskeriProduct->id, 'amount' => 7_000],
        ],
        'by_payment_method_net' => [
            'cash' => ['amount' => 10_000, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    $debitTotal = collect($payload['lines'])->sum('debit_minor');
    $creditTotal = collect($payload['lines'])->sum('credit_minor');

    expect(collect($payload['lines'])->firstWhere('account', '3020')['credit_minor'] ?? null)->toBe(7_000)
        ->and(collect($payload['lines'])->firstWhere('account', '40001')['credit_minor'] ?? null)->toBe(2_700)
        ->and(collect($payload['lines'])->firstWhere('account', '3023')['credit_minor'] ?? null)->toBe(300)
        ->and($debitTotal)->toBe($creditTotal);
});

it('reconciles inflated hybrid product totals to z-report net amount', function () {
    $store = Store::factory()->create();
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
        'basis_key' => '04010',
        'sales_account_no' => '3020',
        'vat_account_no' => '2700',
        'cash_account_no' => '1920',
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'article_group_code' => '04010',
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    $zReport = [
        'net_amount' => 2_991_00,
        'vat_amount' => 598_20,
        'vat_rate' => 25,
        'total_tips' => 0,
        'products_sold' => [
            ['product_id' => $product->id, 'amount' => 5_036_60],
        ],
        'by_payment_method_net' => [
            'card_present' => ['amount' => 2_881_00, 'count' => 1, 'tips' => 0],
            'cash' => ['amount' => 110_00, 'count' => 1, 'tips' => 0],
        ],
    ];

    $payload = app(PowerOfficeLedgerPayloadBuilder::class)->build($session, $integration->fresh('accountMappings'), $zReport);

    $debitTotal = collect($payload['lines'])->sum('debit_minor');
    $creditTotal = collect($payload['lines'])->sum('credit_minor');

    expect(collect($payload['lines'])->firstWhere('account', '3020')['credit_minor'] ?? null)->toBe(2_991_00)
        ->and($debitTotal)->toBe(2_991_00)
        ->and($creditTotal)->toBe(2_991_00);
});

it('builds a balanced preview payload for a closed session', function () {
    $store = Store::factory()->create();
    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'settings' => [
            'ledger' => [
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
        'closing_data' => [
            'z_report_data' => [
                'net_amount' => 5_000,
                'vat_amount' => 1_000,
                'vat_rate' => 25,
                'total_tips' => 0,
                'sales_net_minor_by_vat_rate' => ['25' => 4_000],
                'vat_minor_by_vat_rate' => ['25' => 1_000],
                'by_payment_method_net' => [
                    'cash' => ['amount' => 5_000, 'count' => 1, 'tips' => 0],
                ],
            ],
        ],
    ]);

    $preview = app(PowerOfficeSyncPreviewService::class)->previewZReport($session, $integration);

    expect($preview['ok'])->toBeTrue()
        ->and($preview['balanced'])->toBeTrue()
        ->and($preview['lines_display'])->toHaveCount(2)
        ->and($preview['debit_total_minor'])->toBe(5_000)
        ->and($preview['credit_total_minor'])->toBe(5_000);
});
