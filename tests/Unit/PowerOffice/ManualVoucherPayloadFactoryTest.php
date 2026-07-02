<?php

use App\Services\PowerOffice\PowerOfficeManualVoucherPayloadFactory;

it('builds PowerOffice manual voucher json with signed currency amounts', function () {
    $factory = new PowerOfficeManualVoucherPayloadFactory;

    $body = $factory->build([
        'document_date' => '2026-03-23',
        'description' => 'POS Z-report 1',
        'currency' => 'NOK',
        'department_id' => 20,
        'lines' => [
            [
                'account' => '3000',
                'debit_minor' => 0,
                'credit_minor' => 10_000,
                'description' => 'Sales',
            ],
            [
                'account' => '1920',
                'debit_minor' => 10_000,
                'credit_minor' => 0,
                'description' => 'Cash',
            ],
        ],
    ], [
        '3000' => ['id' => 55, 'vat_code_id' => 9],
        '1920' => ['id' => 66, 'vat_code_id' => null],
    ], 'poweroffice_z_report_1_2', 201);

    expect($body['ExternalImportReference'])->toBe('poweroffice_z_report_1_2')
        ->and($body['CurrencyCode'])->toBe('NOK')
        ->and($body['VoucherLines'])->toHaveCount(2);

    $creditLine = collect($body['VoucherLines'])->firstWhere('AccountId', 55);
    $debitLine = collect($body['VoucherLines'])->firstWhere('AccountId', 66);

    expect($creditLine['CurrencyAmount'])->toBe(-100.0)
        ->and($creditLine['VatId'])->toBe(9)
        ->and($creditLine['DepartmentId'])->toBe(20)
        ->and($debitLine['CurrencyAmount'])->toBe(100.0)
        ->and($debitLine['VatId'])->toBe(201)
        ->and($debitLine['DepartmentId'])->toBe(20);
});

it('includes VatId on supplier reskontro voucher lines', function () {
    $factory = new PowerOfficeManualVoucherPayloadFactory;

    $body = $factory->build([
        'document_date' => '2026-03-23',
        'description' => 'POS Z-report 1',
        'currency' => 'NOK',
        'department_id' => 20,
        'lines' => [
            [
                'account' => '3000',
                'debit_minor' => 0,
                'credit_minor' => 10_000,
                'description' => 'Sales',
            ],
            [
                'account' => '40001',
                'debit_minor' => 0,
                'credit_minor' => 5_000,
                'description' => 'Vendor share',
            ],
            [
                'account' => '1920',
                'debit_minor' => 15_000,
                'credit_minor' => 0,
                'description' => 'Cash',
            ],
        ],
    ], [
        '3000' => ['id' => 55, 'vat_code_id' => 9],
        '40001' => ['id' => 4000101, 'vat_code_id' => 11],
        '1920' => ['id' => 66, 'vat_code_id' => null],
    ], 'poweroffice_z_report_vendor', 201);

    $vendorLine = collect($body['VoucherLines'])->firstWhere('AccountId', 4000101);

    expect($vendorLine['VatId'])->toBe(11)
        ->and($vendorLine['CurrencyAmount'])->toBe(-50.0);
});

it('omits department id when no department is configured', function () {
    $factory = new PowerOfficeManualVoucherPayloadFactory;

    $body = $factory->build([
        'document_date' => '2026-03-23',
        'lines' => [
            [
                'account' => '1920',
                'debit_minor' => 10_000,
                'credit_minor' => 0,
                'description' => 'Cash',
            ],
        ],
    ], [
        '1920' => ['id' => 66, 'vat_code_id' => null],
    ], 'poweroffice_z_report_1_3', 201);

    expect($body['VoucherLines'][0])->not->toHaveKey('DepartmentId')
        ->and($body['VoucherLines'][0]['VatId'])->toBe(201);
});
