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

    $units = QuantityUnit::query()->forSelect()->pluck('name')->all();

    expect($units)->toContain('Piece', 'Kilogram')
        ->and($units)->not->toContain('Store Only');
});

it('includes the currently selected legacy unit in select options', function () {
    seedGlobalQuantityUnits();

    $legacyUnit = QuantityUnit::query()->create([
        'store_id' => Store::factory()->create()->id,
        'stripe_account_id' => null,
        'name' => 'Legacy Unit',
        'symbol' => 'leg',
        'is_standard' => false,
        'active' => false,
    ]);

    $units = QuantityUnit::query()->forSelect($legacyUnit->id)->get();

    expect($units->pluck('id'))->toContain($legacyUnit->id);
    expect(QuantityUnit::labelForId($legacyUnit->id))->toBe('Legacy Unit (leg)');
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
    $response->assertJsonPath('quantity_units.0.name', 'Centimeter');
    expect(collect($response->json('quantity_units'))->pluck('name'))->toContain('Piece');
});

it('includes global units even when is_standard is false', function () {
    $unit = QuantityUnit::query()
        ->whereNull('store_id')
        ->whereNull('stripe_account_id')
        ->where('name', 'Piece')
        ->firstOrFail();

    $unit->update(['is_standard' => false]);

    expect(QuantityUnit::optionsForSelect()[$unit->id])->toBe('Piece (stk)');
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

it('remaps per-store product quantity unit references to matching global units', function () {
    seedGlobalQuantityUnits();

    $globalPiece = QuantityUnit::defaultPiece();
    $storeUnit = QuantityUnit::query()->create([
        'store_id' => Store::factory()->create()->id,
        'stripe_account_id' => null,
        'name' => 'Piece',
        'symbol' => 'stk',
        'is_standard' => true,
        'active' => true,
    ]);

    $product = ConnectedProduct::factory()->create([
        'quantity_unit_id' => $storeUnit->id,
    ]);

    QuantityUnit::remapLegacyProductReferences();

    expect($product->fresh()->quantity_unit_id)->toBe($globalPiece->id);
});
