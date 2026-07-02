<?php

use App\Models\PowerOfficeIntegration;
use App\Services\PowerOffice\PowerOfficeGeneralLedgerAccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('resolves supplier reskontro numbers through suppliers when they are not GL accounts', function () {
    config([
        'poweroffice.client_id' => 'test-poweroffice-application-key',
        'poweroffice.subscription_key' => 'test-ocp-apim-subscription-key',
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create();

    Http::fake(function (Request $request) {
        if (str_contains(strtolower($request->url()), 'oauth/token')) {
            return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
        }

        if (preg_match('#GeneralLedgerAccounts/(\d+)(?:\?|$)#', $request->url(), $matches)) {
            $id = (int) $matches[1];

            return Http::response(match ($id) {
                4000101 => ['Id' => 4000101, 'VatCodeId' => 11],
                4003301 => ['Id' => 4003301, 'VatCodeId' => 11],
                default => ['Id' => $id, 'VatCodeId' => null],
            }, 200);
        }

        if (str_contains($request->url(), 'GeneralLedgerAccounts')) {
            return Http::response([
                ['Id' => 101, 'AccountNo' => 3000, 'VatCodeId' => 3],
                ['Id' => 102, 'AccountNo' => 1920, 'VatCodeId' => null],
            ], 200);
        }

        if (str_contains($request->url(), 'Suppliers')) {
            expect($request->data()['supplierNos'] ?? null)->toBe('40001,40033');

            return Http::response([
                ['Number' => 40001, 'SubledgerAccountId' => 4000101],
                ['Number' => 40033, 'SubledgerAccountId' => 4003301],
            ], 200);
        }

        return Http::response([], 200);
    });

    $resolved = app(PowerOfficeGeneralLedgerAccountResolver::class)
        ->resolveMapForAccountNos($integration, ['3000', '1920', '40001', '40033']);

    expect($resolved['3000'])->toBe(['id' => 101, 'vat_code_id' => 3])
        ->and($resolved['1920'])->toBe(['id' => 102, 'vat_code_id' => null])
        ->and($resolved['40001'])->toBe(['id' => 4000101, 'vat_code_id' => 11])
        ->and($resolved['40033'])->toBe(['id' => 4003301, 'vat_code_id' => 11]);
});
