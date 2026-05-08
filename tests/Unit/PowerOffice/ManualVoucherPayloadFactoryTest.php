<?php

use App\Services\PowerOffice\PowerOfficeManualVoucherPayloadFactory;

it('builds PowerOffice manual voucher json with signed currency amounts', function () {
    $factory = new PowerOfficeManualVoucherPayloadFactory;

    $body = $factory->build([
        'document_date' => '2026-03-23',
        'description' => 'POS Z-report 1',
        'currency' => 'NOK',
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
    ], 'poweroffice_z_report_1_2');

    expect($body['ExternalImportReference'])->toBe('poweroffice_z_report_1_2')
        ->and($body['CurrencyCode'])->toBe('NOK')
        ->and($body['VoucherLines'])->toHaveCount(2);

    $creditLine = collect($body['VoucherLines'])->firstWhere('AccountId', 55);
    $debitLine = collect($body['VoucherLines'])->firstWhere('AccountId', 66);

    expect($creditLine['CurrencyAmount'])->toBe(-100.0)
        ->and($creditLine['VatId'])->toBe(9)
        ->and($debitLine['CurrencyAmount'])->toBe(100.0)
        ->and($debitLine)->not->toHaveKey('VatId');
});
