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

it('resolves gross sales credit buckets per vat rate for the vat basis', function () {
    $z = [
        'sales_net_minor_by_vat_rate' => ['25' => 1_000, '15' => 400],
        'vat_minor_by_vat_rate' => ['25' => 250, '15' => 60],
        'net_amount' => 1_400,
        'vat_amount' => 310,
        'vat_rate' => 25,
    ];

    expect(PowerOfficeStandardVatRates::resolveGrossSalesCreditBucketsForLedger(
        PowerOfficeMappingBasis::Vat,
        $z,
        ['25' => 1_000, '15' => 400],
    ))->toBe(['25' => 1_250, '15' => 460]);
});

it('falls back to single-rate gross bucket when the vat split is missing', function () {
    $z = [
        'net_amount' => 1_000,
        'vat_amount' => 250,
        'vat_rate' => 25,
    ];

    expect(PowerOfficeStandardVatRates::resolveGrossSalesCreditBucketsForLedger(
        PowerOfficeMappingBasis::Vat,
        $z,
        ['25' => 1_000],
    ))->toBe(['25' => 1_250]);
});

it('returns mapping buckets unchanged for non-vat bases because they already hold gross amounts', function () {
    $z = [
        'sales_net_minor_by_vat_rate' => ['25' => 1_000],
        'vat_minor_by_vat_rate' => ['25' => 250],
    ];

    expect(PowerOfficeStandardVatRates::resolveGrossSalesCreditBucketsForLedger(
        PowerOfficeMappingBasis::PaymentMethod,
        $z,
        ['card' => 750, 'cash' => 500],
    ))->toBe(['card' => 750, 'cash' => 500]);
});
