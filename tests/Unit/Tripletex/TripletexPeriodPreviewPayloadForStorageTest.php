<?php

use App\Support\Tripletex\TripletexPeriodPreviewPayloadForStorage;

it('removes nested tripletex voucher payload fields', function (): void {
    $payload = [
        'ok' => true,
        'z_reports' => [
            [
                'preview' => [
                    'ok' => true,
                    'tripletex_voucher_payload' => ['postings' => [['row' => 1]]],
                    'debit_total_minor' => 10,
                ],
            ],
        ],
    ];

    $stripped = TripletexPeriodPreviewPayloadForStorage::removeKeysRecursive($payload, [
        'tripletex_voucher_payload',
        'tripletex_postings_display',
    ]);

    expect($stripped['z_reports'][0]['preview']['tripletex_voucher_payload'] ?? 'gone')->toBe('gone');
});

it('applies progressive compaction when payload exceeds configured max bytes', function (): void {
    $payload = [
        'ok' => true,
        'period' => ['from' => '2026-01-01', 'to' => '2026-01-31', 'store_id' => 1],
        'limits' => [],
        'rollup' => ['z_reports' => ['ok' => 1]],
        'z_reports' => array_fill(0, 5, [
            'pos_session_id' => 1,
            'preview' => [
                'ok' => true,
                'kind' => 'z_report',
                'balanced' => true,
                'debit_total_minor' => 100,
                'credit_total_minor' => 100,
                'line_kinds' => ['a' => ['debit_minor' => 100, 'credit_minor' => 0]],
                'lines_display' => array_fill(0, 30, ['account' => '3000', 'description' => str_repeat('x', 200), 'debit' => 1.0, 'credit' => 0.0]),
            ],
        ]),
        'payouts' => [],
    ];

    [$stored, $meta] = TripletexPeriodPreviewPayloadForStorage::prepare($payload, false, 500);

    $len = strlen((string) json_encode($stored, JSON_INVALID_UTF8_SUBSTITUTE));

    expect($len)->toBeLessThanOrEqual(500)
        ->and($meta['steps'])->not->toBeEmpty();

    expect($stored['rollup'] ?? null)->toBeArray();
});
