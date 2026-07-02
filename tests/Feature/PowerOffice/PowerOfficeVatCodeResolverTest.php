<?php

use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Services\PowerOffice\PowerOfficeVatCodeResolver;
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

it('resolves the zero vat code id from PowerOffice', function () {
    Http::fake(function (Request $request) {
        if (str_contains(strtolower($request->url()), 'oauth/token')) {
            return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
        }

        if (str_contains($request->url(), 'VatCodes')) {
            return Http::response([
                ['Id' => 201, 'Code' => '0'],
                ['Id' => 9, 'Code' => '3'],
            ], 200);
        }

        return Http::response([], 404);
    });

    $store = Store::factory()->create();
    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
    ]);

    $resolver = app(PowerOfficeVatCodeResolver::class);

    expect($resolver->resolveZeroVatId($integration))->toBe(201)
        ->and($resolver->resolveIdForCode($integration, '3'))->toBe(9);
});

it('throws when PowerOffice has no zero vat code', function () {
    Http::fake(function (Request $request) {
        if (str_contains(strtolower($request->url()), 'oauth/token')) {
            return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
        }

        if (str_contains($request->url(), 'VatCodes')) {
            return Http::response([
                ['Id' => 9, 'Code' => '3'],
            ], 200);
        }

        return Http::response([], 404);
    });

    $store = Store::factory()->create();
    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
    ]);

    app(PowerOfficeVatCodeResolver::class)->resolveZeroVatId($integration);
})->throws(RuntimeException::class, 'no VAT code "0"');
