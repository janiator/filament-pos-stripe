<?php

use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Services\PowerOffice\PowerOfficeApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'poweroffice.client_id' => 'test-poweroffice-application-key',
        'poweroffice.subscription_key' => 'test-ocp-apim-subscription-key',
    ]);
});

it('uploads journal entry voucher pages when direct posting is disabled', function () {
    Http::fake(function (Request $request) {
        if (preg_match('#/OAuth/Token#i', $request->url()) || str_contains(strtolower($request->url()), 'oauth/token')) {
            return Http::response([
                'access_token' => 'fake-poweroffice-access-token',
                'expires_in' => 3600,
            ], 200);
        }

        if (str_contains($request->url(), 'VoucherPages')) {
            return Http::response('', 201);
        }

        return Http::response('', 404);
    });

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => Store::factory(),
        'settings' => ['voucher_posting_mode' => 'journal_entry'],
    ]);

    $response = app(PowerOfficeApiClient::class)->attachZReportPdf(
        $integration,
        '07d35a2d-d985-47cb-9fc5-dcf89c9c4831',
        '%PDF-1.3 fake',
        'Z-test.pdf',
    );

    expect($response->successful())->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/JournalEntryVouchers/07d35a2d-d985-47cb-9fc5-dcf89c9c4831/VoucherPages'));
});

it('uploads posted voucher documentation when direct posting is enabled', function () {
    Http::fake(function (Request $request) {
        if (preg_match('#/OAuth/Token#i', $request->url()) || str_contains(strtolower($request->url()), 'oauth/token')) {
            return Http::response([
                'access_token' => 'fake-poweroffice-access-token',
                'expires_in' => 3600,
            ], 200);
        }

        if (str_contains($request->url(), 'VoucherDocumentation')) {
            return Http::response('', 204);
        }

        return Http::response('', 404);
    });

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => Store::factory(),
        'settings' => ['voucher_posting_mode' => 'direct'],
    ]);

    $response = app(PowerOfficeApiClient::class)->attachZReportPdf(
        $integration,
        '07d35a2d-d985-47cb-9fc5-dcf89c9c4831',
        '%PDF-1.3 fake',
        'Z-test.pdf',
    );

    expect($response->successful())->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && str_contains($request->url(), 'VoucherDocumentation?id=07d35a2d-d985-47cb-9fc5-dcf89c9c4831'));
});
