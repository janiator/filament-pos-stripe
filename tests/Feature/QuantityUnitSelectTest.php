<?php

use App\Models\ConnectedProduct;
use App\Models\QuantityUnit;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\QuantityUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function seedGlobalQuantityUnits(): void
{
    (new QuantityUnitSeeder)->run();
}

it('returns global standard units for product selects', function () {
    seedGlobalQuantityUnits();

    QuantityUnit::query()->create([
        'store_id' => Store::factory()->create()->id,
        'stripe_account_id' => 'acct_store_only',
        'name' => 'Store Only',
        'symbol' => 'so',
        'is_standard' => false,
        'active' => true,
    ]);

    $units = array_values(QuantityUnit::optionsForCatalog());

    expect($units)->toContain('Piece (stk)', 'Kilogram (kg)')
        ->and($units)->not->toContain('Store Only');
});

it('includes store units in catalog options for that store', function () {
    seedGlobalQuantityUnits();

    $store = Store::factory()->create();
    $storeUnit = QuantityUnit::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => null,
        'name' => 'Store Only',
        'symbol' => 'so',
        'is_standard' => false,
        'active' => true,
    ]);

    $options = QuantityUnit::optionsForCatalog($store->id);

    expect($options)->toHaveKey((string) $storeUnit->id)
        ->and($options)->toHaveKey((string) QuantityUnit::defaultPieceId());
});

it('maps missing legacy ids to the default piece when globals exist', function () {
    seedGlobalQuantityUnits();

    expect(QuantityUnit::resolveReplacementId(999999))->toBe(QuantityUnit::defaultPieceId());
});

it('includes global units even when is_standard is false', function () {
    $unit = QuantityUnit::query()
        ->whereNull('store_id')
        ->whereNull('stripe_account_id')
        ->where('name', 'Piece')
        ->firstOrFail();

    $unit->update(['is_standard' => false]);

    expect(QuantityUnit::optionsForCatalog()[(string) $unit->id])->toBe('Piece (stk)');
});

it('updates existing global units when defaults are imported again', function () {
    $unit = QuantityUnit::query()
        ->whereNull('store_id')
        ->whereNull('stripe_account_id')
        ->where('name', 'Piece')
        ->firstOrFail();

    $unit->update([
        'is_standard' => false,
        'active' => false,
        'description' => null,
    ]);

    seedGlobalQuantityUnits();

    $unit->refresh();

    expect($unit->is_standard)->toBeTrue()
        ->and($unit->active)->toBeTrue()
        ->and($unit->description)->toBe('Per item/piece');
});

it('lists global quantity units from the api', function () {
    seedGlobalQuantityUnits();

    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_api_units']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/quantity-units');

    $response->assertOk();
    expect(collect($response->json('quantity_units'))->pluck('name'))->toContain('Piece');
});

it('remaps products linked to stripe-scoped legacy units', function () {
    seedGlobalQuantityUnits();

    $store = Store::factory()->create(['stripe_account_id' => 'acct_legacy_unit']);
    $globalPiece = QuantityUnit::defaultPiece();
    $legacyUnit = QuantityUnit::query()->create([
        'store_id' => null,
        'stripe_account_id' => 'acct_legacy_unit',
        'name' => 'Piece',
        'symbol' => 'stk',
        'is_standard' => true,
        'active' => true,
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'quantity_unit_id' => $legacyUnit->id,
    ]);

    expect(QuantityUnit::remapLegacyProductReferences())->toBe(1)
        ->and($product->fresh()->quantity_unit_id)->toBe($globalPiece->id);
});

it('remaps per-store product quantity unit references to matching global units', function () {
    seedGlobalQuantityUnits();

    $store = Store::factory()->create(['stripe_account_id' => 'acct_remap_store']);
    $globalPiece = QuantityUnit::defaultPiece();
    $storeUnit = QuantityUnit::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => null,
        'name' => 'Piece',
        'symbol' => 'stk',
        'is_standard' => true,
        'active' => true,
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'quantity_unit_id' => $storeUnit->id,
    ]);

    expect(QuantityUnit::remapLegacyProductReferences())->toBe(1)
        ->and($product->fresh()->quantity_unit_id)->toBe($globalPiece->id);
});
