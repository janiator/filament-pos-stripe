<?php

use App\Services\PowerOffice\PowerOfficeApiClient;
use Illuminate\Support\Facades\Http;

it('summarizes PowerOffice validation details instead of only the generic title', function () {
    Http::fake([
        '*' => Http::response([
            'title' => 'Aggregated (multiple) Validation Exception',
            'detail' => 'Supplier validation error(s)',
            'errors' => [
                'Number' => ['The supplier number must be within the supplier number series.'],
                'Name' => ['The supplier name is required.'],
            ],
        ], 400),
    ]);

    $response = Http::get('https://poweroffice.test/suppliers');

    $summary = app(PowerOfficeApiClient::class)->summarizeErrorBody($response);

    expect($summary)->toContain('Aggregated (multiple) Validation Exception')
        ->and($summary)->toContain('Supplier validation error(s)')
        ->and($summary)->toContain('Number: The supplier number must be within the supplier number series.')
        ->and($summary)->toContain('Name: The supplier name is required.');
});
