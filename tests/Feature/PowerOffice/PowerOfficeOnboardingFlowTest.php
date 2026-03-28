<?php

use App\Enums\AddonType;
use App\Enums\PowerOfficeIntegrationStatus;
use App\Models\Addon;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = Store::factory()->create();
    $this->user->stores()->attach($this->store);
    $this->user->setCurrentStore($this->store);
    Sanctum::actingAs($this->user, ['*']);
});

it('rejects onboarding init when PowerOffice add-on is disabled', function () {
    $response = $this->postJson('/api/poweroffice/onboarding/init');

    $response->assertForbidden();
});

it('rejects onboarding init when PowerOffice client id is not configured', function () {
    Addon::query()->create([
        'store_id' => $this->store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);

    config(['poweroffice.client_id' => null]);

    $response = $this->postJson('/api/poweroffice/onboarding/init');

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('POWEROFFICE_CLIENT_ID');

    $integration = PowerOfficeIntegration::query()->where('store_id', $this->store->id)->first();
    expect($integration)->not->toBeNull()
        ->and($integration->status)->toBe(PowerOfficeIntegrationStatus::PendingOnboarding)
        ->and($integration->onboarding_state_token)->not->toBeNull();
});

it('starts onboarding and returns a url when client id is configured', function () {
    Addon::query()->create([
        'store_id' => $this->store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);

    config([
        'poweroffice.client_id' => 'test-client',
        'poweroffice.client_secret' => 'test-secret',
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'https://goapi.poweroffice.net/demo/*' => \Illuminate\Support\Facades\Http::response([
            'TemporaryUrl' => 'https://poweroffice.example/onboard/123',
        ], 200),
    ]);

    $response = $this->postJson('/api/poweroffice/onboarding/init');

    $response->assertSuccessful();
    $response->assertJson([
        'onboarding_url' => 'https://poweroffice.example/onboard/123',
    ]);

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        if (! str_contains($request->url(), 'Onboarding/Initiate')) {
            return false;
        }

        $data = $request->data();

        return ($data['ApplicationKey'] ?? null) === 'test-client'
            && is_string($data['RedirectUri'] ?? null)
            && str_contains((string) $data['RedirectUri'], 'state=');
    });

    $integration = PowerOfficeIntegration::query()->where('store_id', $this->store->id)->first();
    expect($integration)->not->toBeNull()
        ->and($integration->status)->toBe(PowerOfficeIntegrationStatus::PendingOnboarding)
        ->and($integration->onboarding_state_token)->not->toBeNull();
});

it('completes onboarding via callback using state and token', function () {
    config(['poweroffice.client_id' => null]);

    $integration = PowerOfficeIntegration::factory()->create([
        'store_id' => $this->store->id,
        'onboarding_state_token' => 'test-state-token',
        'status' => PowerOfficeIntegrationStatus::PendingOnboarding,
    ]);

    \Illuminate\Support\Facades\Cache::put('poweroffice_onboarding:test-state-token', $this->store->id, 3600);

    $response = $this->postJson('/api/poweroffice/onboarding/callback', [
        'state' => 'test-state-token',
        'token' => 'exchange-token',
    ]);

    $response->assertSuccessful();

    $integration->refresh();
    expect($integration->status)->toBe(PowerOfficeIntegrationStatus::Connected)
        ->and($integration->client_key)->not->toBeNull();
});

it('posts v2 finalize payload when completing onboarding with client id configured', function () {
    config([
        'poweroffice.client_id' => 'test-app-key',
        'poweroffice.client_secret' => 'test-secret',
    ]);

    $integration = PowerOfficeIntegration::factory()->create([
        'store_id' => $this->store->id,
        'onboarding_state_token' => 'test-state-token',
        'status' => PowerOfficeIntegrationStatus::PendingOnboarding,
    ]);

    \Illuminate\Support\Facades\Cache::put('poweroffice_onboarding:test-state-token', $this->store->id, 3600);

    \Illuminate\Support\Facades\Http::fake([
        'https://goapi.poweroffice.net/demo/*' => \Illuminate\Support\Facades\Http::response([
            'OnboardedClientsInformation' => [
                [
                    'ClientKey' => 'f1ba4158-7bbc-4ecc-a68d-1a8ac42c5480',
                    'ClientName' => 'Test AS',
                ],
            ],
        ], 200),
    ]);

    $response = $this->postJson('/api/poweroffice/onboarding/callback', [
        'state' => 'test-state-token',
        'token' => 'exchange-token',
    ]);

    $response->assertSuccessful();

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        if (! str_contains($request->url(), 'Onboarding/Finalize')) {
            return false;
        }

        $data = $request->data();

        return ($data['OnboardingToken'] ?? null) === 'exchange-token';
    });

    $integration->refresh();
    expect($integration->client_key)->toBe('f1ba4158-7bbc-4ecc-a68d-1a8ac42c5480');
});
