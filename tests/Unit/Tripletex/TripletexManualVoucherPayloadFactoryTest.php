<?php

use App\Services\Tripletex\TripletexManualVoucherPayloadFactory;

it('copies vatType id from resolved Tripletex account when line omits tripletex_vat_type_id', function (): void {
    $factory = app(TripletexManualVoucherPayloadFactory::class);
    $accountMap = [
        '3001' => [
            'id' => 324366348,
            'number' => 3001,
            'name' => 'Kiosksalg',
            'vatLocked' => true,
            'vatType' => ['id' => 31, 'url' => 'tripletex.no/v2/ledger/vatType/31'],
        ],
    ];

    $voucher = $factory->build([
        'document_date' => '2026-05-08',
        'currency' => 'NOK',
        'description' => 'POS Z-report',
        'lines' => [
            [
                'account' => '3001',
                'debit_minor' => 0,
                'credit_minor' => 100,
                'description' => 'Sales',
            ],
        ],
    ], $accountMap);

    expect($voucher['postings'][0]['vatType']['id'])->toBe(31);
});

it('uses explicit tripletex_vat_type_id over account default', function (): void {
    $factory = app(TripletexManualVoucherPayloadFactory::class);
    $accountMap = [
        '3001' => [
            'id' => 1,
            'number' => 3001,
            'vatType' => ['id' => 31],
        ],
    ];

    $voucher = $factory->build([
        'document_date' => '2026-05-08',
        'currency' => 'NOK',
        'description' => 'Test',
        'lines' => [
            [
                'account' => '3001',
                'debit_minor' => 0,
                'credit_minor' => 100,
                'tripletex_vat_type_id' => 44,
                'description' => 'Sales',
            ],
        ],
    ], $accountMap);

    expect($voucher['postings'][0]['vatType']['id'])->toBe(44);
});

it('allows explicit vat type zero for locked clearing-style accounts', function (): void {
    $factory = app(TripletexManualVoucherPayloadFactory::class);
    $accountMap = [
        '1901' => [
            'id' => 2,
            'number' => 1901,
            'vatType' => ['id' => 0],
            'vatLocked' => true,
        ],
    ];

    $voucher = $factory->build([
        'document_date' => '2026-05-08',
        'currency' => 'NOK',
        'description' => 'Test',
        'lines' => [
            [
                'account' => '1901',
                'debit_minor' => 100,
                'credit_minor' => 0,
                'tripletex_vat_type_id' => 0,
                'description' => 'Debit',
            ],
        ],
    ], $accountMap);

    expect($voucher['postings'][0]['vatType']['id'])->toBe(0);
});

it('omits posting vatType when account has no vatType id and line has no override', function (): void {
    $factory = app(TripletexManualVoucherPayloadFactory::class);
    $accountMap = [
        '3000' => ['id' => 1001, 'number' => 3000, 'name' => 'Sales'],
    ];

    $voucher = $factory->build([
        'document_date' => '2026-05-08',
        'currency' => 'NOK',
        'description' => 'Test',
        'lines' => [
            [
                'account' => '3000',
                'debit_minor' => 0,
                'credit_minor' => 100,
                'description' => 'Line',
            ],
        ],
    ], $accountMap);

    expect($voucher['postings'][0])->not->toHaveKey('vatType');
});
