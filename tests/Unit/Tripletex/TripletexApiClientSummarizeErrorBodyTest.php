<?php

use App\Services\Tripletex\TripletexApiClient;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;

it('appends nested validation messages to tripletex 422 summaries', function (): void {
    $json = [
        'message' => 'Validering feilet.',
        'validationMessages' => [
            ['field' => 'postings[0].vatType', 'message' => 'MVA-type mangler for salgspost.'],
        ],
    ];
    $psr = new Psr7Response(422, ['Content-Type' => 'application/json'], json_encode($json, JSON_THROW_ON_ERROR));
    $response = new Response($psr);

    $summary = (new TripletexApiClient)->summarizeErrorBody($response);

    expect($summary)->toContain('Validering feilet')
        ->and($summary)->toContain('MVA-type mangler')
        ->and($summary)->toContain('postings[0].vatType');
});

it('reads validation messages nested under value', function (): void {
    $json = [
        'message' => 'Validering feilet.',
        'value' => [
            'validationMessages' => [
                ['message' => 'Bilag er ikke balansert.'],
            ],
        ],
    ];
    $psr = new Psr7Response(422, ['Content-Type' => 'application/json'], json_encode($json, JSON_THROW_ON_ERROR));
    $response = new Response($psr);

    $summary = (new TripletexApiClient)->summarizeErrorBody($response);

    expect($summary)->toContain('Validering feilet')
        ->and($summary)->toContain('Bilag er ikke balansert');
});

it('describe failed voucher response includes pretty printed body', function (): void {
    $json = [
        'message' => 'Validering feilet.',
        'code' => 422,
    ];
    $psr = new Psr7Response(422, ['Content-Type' => 'application/json'], json_encode($json, JSON_THROW_ON_ERROR));
    $response = new Response($psr);

    $desc = (new TripletexApiClient)->describeFailedVoucherResponse($response);

    expect($desc)->toContain('Tripletex HTTP 422')
        ->and($desc)->toContain('Validering feilet')
        ->and($desc)->toContain('Tripletex response body:')
        ->and($desc)->toContain('"code"')
        ->and($desc)->toContain('"message"');
});

it('truncates oversized voucher error bodies', function (): void {
    $long = str_repeat('x', 500);
    $json = ['message' => 'Err', 'blob' => $long];
    $psr = new Psr7Response(422, ['Content-Type' => 'application/json'], json_encode($json, JSON_THROW_ON_ERROR));
    $response = new Response($psr);

    $desc = (new TripletexApiClient)->describeFailedVoucherResponse($response, maxBodyChars: 120);

    expect($desc)->toContain('truncated')
        ->and(mb_strlen($desc))->toBeLessThan(800);
});
