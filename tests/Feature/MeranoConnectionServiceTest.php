<?php

use App\Models\Store;
use App\Services\MeranoConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('reports missing Merano configuration before testing', function () {
    $store = Store::factory()->create([
        'merano_base_url' => null,
        'merano_pos_api_token' => null,
    ]);

    $result = app(MeranoConnectionService::class)->testConnection($store);

    expect($result['ok'])->toBeFalse();
    expect($result['status'])->toBeNull();
    expect($result['message'])->toContain('Merano base URL and POS API token must both be configured');
});

it('tests the Merano connection successfully and counts returned events', function () {
    $store = Store::factory()->create([
        'merano_base_url' => 'https://merano.example.com',
        'merano_pos_api_token' => 'merano-token',
    ]);

    Http::fake([
        'https://merano.example.com/api/pos/v1/events' => Http::response([
            'data' => [
                ['id' => 1, 'name' => 'Show 1'],
                ['id' => 2, 'name' => 'Show 2'],
            ],
        ], 200),
    ]);

    $result = app(MeranoConnectionService::class)->testConnection($store);

    expect($result['ok'])->toBeTrue();
    expect($result['status'])->toBe(200);
    expect($result['events_count'])->toBe(2);
    expect($result['message'])->toContain('Connection OK');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://merano.example.com/api/pos/v1/events'
            && ($request->header('X-POS-API-Token')[0] ?? null) === 'merano-token'
            && str_contains($request->header('Authorization')[0] ?? '', 'Bearer merano-token');
    });
});

it('returns a helpful failure when Merano responds with an error', function () {
    $store = Store::factory()->create([
        'merano_base_url' => 'https://merano.example.com',
        'merano_pos_api_token' => 'merano-token',
    ]);

    Http::fake([
        'https://merano.example.com/api/pos/v1/events' => Http::response([
            'message' => 'Unauthorized',
        ], 401),
    ]);

    $result = app(MeranoConnectionService::class)->testConnection($store);

    expect($result['ok'])->toBeFalse();
    expect($result['status'])->toBe(401);
    expect($result['message'])->toContain('HTTP 401');
});

it('reports that the store schema supports Merano configuration after migrations', function () {
    expect(app(MeranoConnectionService::class)->supportsStoreConfiguration())->toBeTrue();
});
