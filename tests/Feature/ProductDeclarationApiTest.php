<?php

use App\Models\ProductDeclaration;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('product declaration show json formats declaration_date', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    ProductDeclaration::query()->create([
        'store_id' => $store->id,
        'product_name' => 'Test Product',
        'vendor_name' => 'Vendor',
        'version' => '1.0.0',
        'version_identification' => 'TEST-1',
        'declaration_date' => '2026-05-16',
        'content' => 'Body',
        'is_active' => true,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/product-declaration');

    $response->assertOk();
    $response->assertJsonPath('data.declaration_date', '2026-05-16');
});

test('resolvedDeclarationDate parses string when attribute is stored without casting', function () {
    $declaration = new ProductDeclaration;

    ProductDeclaration::withoutCasting(function () use ($declaration): void {
        $declaration->forceFill([
            'store_id' => 1,
            'product_name' => 'Test Product',
            'vendor_name' => 'Vendor',
            'version' => '1.0.0',
            'version_identification' => 'TEST-1',
            'declaration_date' => '2026-05-16',
            'content' => 'Body',
            'is_active' => true,
        ]);
    });

    expect($declaration->resolvedDeclarationDate())->not->toBeNull();
    expect($declaration->resolvedDeclarationDate()?->format('Y-m-d'))->toBe('2026-05-16');
});
