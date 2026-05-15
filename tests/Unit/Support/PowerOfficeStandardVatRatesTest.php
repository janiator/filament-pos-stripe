<?php

use App\Enums\PowerOfficeMappingBasis;
use App\Support\PowerOffice\PowerOfficeStandardVatRates;

it('returns string basis keys even when PHP casts numeric keys on options', function () {
    expect(PowerOfficeStandardVatRates::basisKeys())->toBe(['0', '15', '25']);
});

it('normalizes sales net split maps to canonical string keys', function () {
    $out = PowerOfficeStandardVatRates::normalizeSalesNetMinorByVatRateMap([15 => 100, '25' => 200, '99' => 1]);

    expect($out)->toHaveKeys(['15', '25'])
        ->and($out['15'])->toBe(100)
        ->and($out['25'])->toBe(200);
});

it('resolves sales credit buckets from split before empty mapping buckets', function () {
    $mapping = [];
    $z = [
        'sales_net_minor_by_vat_rate' => ['15' => 500],
        'net_amount' => 500,
        'vat_rate' => 15,
    ];

    expect(PowerOfficeStandardVatRates::resolveSalesCreditBucketsForLedger(
        PowerOfficeMappingBasis::PaymentMethod,
        $z,
        $mapping,
    ))->toBe(['15' => 500]);
});
