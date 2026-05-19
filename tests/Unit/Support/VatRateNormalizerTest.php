<?php

use App\Support\VatRateNormalizer;

it('normalizes negative VAT rates to zero', function () {
    expect(VatRateNormalizer::toDecimal(-15))->toBe(0.0);
});

it('does not expose unused VAT tax extraction helpers', function () {
    $contents = file_get_contents(dirname(__DIR__, 3).'/app/Support/VatRateNormalizer.php');

    expect($contents)->not->toContain('extractTaxOreFromInclusiveAmount');
});

it('keeps FlutterFlow tax rate normalizers aligned with PHP clamping', function (string $path) {
    $contents = file_get_contents(dirname(__DIR__, 3).'/'.$path);

    expect($contents)
        ->toContain('if (rate <= 0)')
        ->not->toContain('if (rate == 0)');
})->with([
    'docs/flutterflow/custom-actions/cart_tax_rate_helpers.dart',
    'docs/flutterflow/custom-actions/complete_pos_purchase.dart',
    'docs/flutterflow/custom-actions/prepare_parked_deferred_purchase.dart',
    'docs/flutterflow/custom-actions/serialize_cart_for_complete_deferred.dart',
    'docs/flutterflow/custom-actions/update_cart_totals.dart',
]);
