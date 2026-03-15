<?php

use App\Filament\Widgets\TopProductsWidget;
use App\Models\ConnectedCharge;
use App\Models\PosSession;
use App\Models\Store;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it allocates cart level discounts to product revenue', function () {
    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_top_products_discounts',
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'paid_at' => now(),
        'amount' => 15000,
        'metadata' => [
            'items' => [
                [
                    'product_id' => 1,
                    'name' => 'Product A',
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
                [
                    'product_id' => 2,
                    'name' => 'Product B',
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
            ],
            'total' => 15000,
            'total_discounts' => 5000,
        ],
    ]);

    Filament::shouldReceive('getTenant')
        ->once()
        ->andReturn($store);

    $widget = new class extends TopProductsWidget
    {
        public function exposedData(): array
        {
            return $this->getData();
        }
    };

    $data = $widget->exposedData();
    $revenueByLabel = array_combine($data['labels'], $data['datasets'][0]['data']);

    expect($revenueByLabel)
        ->toHaveKey('Product A')
        ->toHaveKey('Product B');

    expect($revenueByLabel['Product A'])->toBe(75.0)
        ->and($revenueByLabel['Product B'])->toBe(75.0);
});
