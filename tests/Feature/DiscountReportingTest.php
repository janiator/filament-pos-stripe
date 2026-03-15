<?php

use App\Filament\Resources\PosPurchases\Schemas\PosPurchaseInfolist;
use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Receipt;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses net amounts for z-report product and vendor aggregates when discounts exist', function () {
    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_discount_aggregation',
    ]);
    $user = User::factory()->create();
    $device = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $device->id,
        'user_id' => $user->id,
        'status' => 'closed',
        'opened_at' => now()->subHour(),
        'closed_at' => now(),
    ]);

    $vendor = Vendor::create([
        'store_id' => $store->id,
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'Discount Vendor',
        'active' => true,
        'commission_percent' => 10,
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'vendor_id' => $vendor->id,
        'name' => 'Freeticket Product',
    ]);

    $charge = ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'amount' => 0,
        'status' => 'succeeded',
        'paid' => true,
        'paid_at' => now(),
        'payment_method' => 'freeticket',
        'metadata' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 198000,
                ],
            ],
            'total_discounts' => 198000,
            'subtotal' => 198000,
            'total' => 0,
        ],
    ]);

    Receipt::factory()->create([
        'store_id' => $store->id,
        'pos_session_id' => $session->id,
        'charge_id' => $charge->id,
        'receipt_type' => 'sales',
        'receipt_data' => [
            'items' => [
                [
                    'name' => 'Freeticket Product',
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 198000,
                ],
            ],
            'total_discounts' => 198000,
            'subtotal' => 198000,
            'total' => 0,
        ],
    ]);

    $report = PosSessionsTable::generateZReport($session, attachMissingData: false);

    expect($report['products_sold'])->not->toBeEmpty();
    expect($report['products_sold'][0]['amount'])->toBe(0);
    expect($report['sales_by_vendor'])->not->toBeEmpty();
    expect($report['sales_by_vendor'][0]['amount'])->toBe(0);
    expect($report['sales_by_vendor'][0]['commission_amount'])->toBe(0);
});

it('derives purchase discounts for filament infolist from subtotal minus total when metadata total_discounts is missing', function () {
    $record = new class
    {
        public array $metadata = [
            'items' => [
                [
                    'quantity' => 1,
                    'unit_price' => 198000,
                ],
            ],
            'subtotal' => 198000,
            'total' => 0,
        ];

        public int $amount = 0;
    };

    expect(PosPurchaseInfolist::resolveTotalDiscountsOreForDisplay($record))->toBe(198000);
});
