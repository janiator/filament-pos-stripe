<?php

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'poweroffice.client_id' => 'test-application-key',
        'poweroffice.subscription_key' => 'test-subscription-key',
    ]);
});

it('prints client integration information when PowerOffice returns 200', function () {
    Http::fake(function (Request $request) {
        if (preg_match('#/OAuth/Token#i', $request->url())) {
            return Http::response([
                'access_token' => 'fake-access-token',
                'expires_in' => 3600,
            ], 200);
        }
        if (str_contains($request->url(), 'ClientIntegrationInformation')) {
            return Http::response([
                'ValidPrivileges' => ['GeneralLedgerAccounts_Full'],
                'InvalidPrivileges' => [],
            ], 200);
        }

        return Http::response('Not Found', 404);
    });

    $store = Store::factory()->create(['slug' => 'diagnose-store']);
    Addon::query()->create([
        'store_id' => $store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);
    PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
    ]);

    $this->artisan('poweroffice:diagnose', ['store_slug' => 'diagnose-store'])
        ->assertSuccessful()
        ->expectsOutputToContain('ValidPrivileges');
});
