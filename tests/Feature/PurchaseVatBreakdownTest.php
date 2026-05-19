<?php

use App\Filament\Resources\PosPurchases\Schemas\PosPurchaseInfolist;
use App\Models\ArticleGroupCode;
use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PaymentMethod;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
use App\Services\ReceiptTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('resolves 15 percent vat on purchase items when client sends wrong tax rate', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_vat_15']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    ArticleGroupCode::query()->create([
        'code' => 'EGG',
        'name' => 'Egg',
        'default_vat_percent' => 0.15,
        'active' => true,
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'article_group_code' => 'EGG',
        'vat_percent' => 15.00,
    ]);

    $posDevice = PosDevice::factory()->create(['store_id' => $store->id, 'cash_drawer_enabled' => true]);
    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $posDevice->id,
        'user_id' => $user->id,
        'status' => 'open',
    ]);

    PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'Cash',
        'code' => 'cash',
        'provider' => 'cash',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'minimum_amount_kroner' => null,
        'saf_t_payment_code' => '10000',
        'saf_t_event_code' => '13016',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'cash',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 4500,
                    'tax_rate' => 0.25,
                    'tax_inclusive' => true,
                ],
            ],
            'subtotal' => 3913,
            'total_tax' => 587,
            'total_discounts' => 0,
            'total' => 4500,
            'currency' => 'nok',
        ],
    ]);

    $response->assertStatus(201);

    $charge = ConnectedCharge::query()->latest('id')->first();
    expect($charge)->not->toBeNull();

    $items = $charge->metadata['items'] ?? [];
    expect($items)->toHaveCount(1)
        ->and((float) $items[0]['tax_rate'])->toBe(0.15);

    $breakdown = PosPurchaseInfolist::buildTaxBreakdownForRecord($charge);
    expect($breakdown)->toHaveCount(1)
        ->and($breakdown[0]['rate'])->toBe('15%')
        ->and($breakdown[0]['amount'])->toBe('5.87 NOK');
});

it('builds receipt vat breakdown at 15 percent for food items', function () {
    $store = Store::factory()->create();
    $charge = ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'amount' => 4500,
        'metadata' => [
            'subtotal' => 3913,
            'total_tax' => 587,
            'items' => [
                [
                    'unit_price' => 4500,
                    'quantity' => 1,
                    'discount_amount' => 0,
                    'tax_rate' => 0.15,
                    'product_name' => 'Egg product',
                ],
            ],
        ],
    ]);

    $service = app(ReceiptTemplateService::class);
    $reflection = new ReflectionMethod($service, 'buildVatBreakdownFromItems');
    $reflection->setAccessible(true);

    $result = $reflection->invoke($service, $charge->metadata['items'], 45.0, $charge->metadata);

    expect($result)->toHaveCount(1)
        ->and($result[0]['vat_rate'])->toBe(15.0)
        ->and($result[0]['vat_base'])->toBe(39.13)
        ->and($result[0]['vat_amount'])->toBe(5.87);
});

it('exposes purchase item tax rate in purchase api response', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_vat_api']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'vat_percent' => 15.00,
    ]);

    $charge = ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'amount' => 4500,
        'metadata' => [
            'items' => [
                [
                    'id' => 'line-1',
                    'product_id' => $product->id,
                    'unit_price' => 4500,
                    'quantity' => 1,
                    'discount_amount' => 0,
                    'tax_rate' => 0.15,
                ],
            ],
            'subtotal' => 3913,
            'total_tax' => 587,
        ],
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson("/api/purchases/{$charge->id}");

    $response->assertOk();
    expect($response->json('purchase.purchase_items.0.purchase_item_tax_rate'))->toBe(0.15);
});
