<?php

/**
 * Mirrors the period preview Blade formatter (minor → major, 2 decimals, grouped thousands).
 */
describe('tripletex period preview amount display', function (): void {
    it('converts minor units to a grouped major amount string', function (): void {
        $fmtMinorToMajor = static fn (mixed $minor): string => number_format(((int) ($minor ?? 0)) / 100, 2);

        expect($fmtMinorToMajor(54360530))->toBe('543,605.30')
            ->and($fmtMinorToMajor(1347087216))->toBe('13,470,872.16')
            ->and($fmtMinorToMajor(0))->toBe('0.00');
    });
});
