<?php

use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PaymentMethod;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
use App\Support\MeranoTicketPurchaseMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('syncInto clears stale ticket metadata when cart no longer has bookings', function () {
    $metadata = [
        'purchase_contains_tickets' => true,
        'purchase_ticket_reference' => 'BK-OLD',
    ];

    MeranoTicketPurchaseMetadata::syncInto($metadata, [
        [
            'id' => 'kiosk_1',
            'name' => 'Popcorn',
            'quantity' => 1,
            'unit_price' => 5000,
        ],
    ]);

    expect($metadata)->not->toHaveKey('purchase_contains_tickets');
    expect($metadata)->not->toHaveKey('purchase_ticket_reference');
});

test('merano ticket metadata is derived from cart line metadata', function () {
    $metadata = [];

    MeranoTicketPurchaseMetadata::mergeInto($metadata, [
        [
            'id' => 'ticket_line_1',
            'name' => 'Billett',
            'metadata' => [
                'merano_booking_id' => 42,
                'merano_booking_number' => 'BK-2026-000999',
            ],
        ],
    ]);

    expect($metadata)->toMatchArray([
        'purchase_contains_tickets' => true,
        'purchase_ticket_reference' => 'BK-2026-000999',
    ]);
});

test('purchase show exposes merano ticket metadata when stored on line items only', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_merano_ticket_meta']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $posDevice = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $posDevice->id,
        'user_id' => $user->id,
        'status' => 'open',
    ]);

    $charge = ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'paid' => true,
        'status' => 'succeeded',
        'metadata' => [
            'items' => [
                [
                    'id' => 'ticket_line_1',
                    'name' => 'Billett',
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'metadata' => [
                        'merano_booking_id' => 99,
                        'merano_booking_number' => 'BK-2026-REPRINT',
                    ],
                ],
            ],
        ],
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson("/api/purchases/{$charge->id}");

    $response->assertOk();
    $response->assertJsonPath('purchase.purchase_metadata.purchase_contains_tickets', true);
    $response->assertJsonPath('purchase.purchase_metadata.purchase_ticket_reference', 'BK-2026-REPRINT');
});

test('revise-deferred clears ticket metadata when tickets are removed from cart', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_merano_revise_'.uniqid()]);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $posDevice = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $posDevice->id,
        'user_id' => $user->id,
        'status' => 'open',
    ]);

    PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'Betaling ved henting',
        'code' => 'deferred',
        'provider' => 'other',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'minimum_amount_kroner' => null,
        'saf_t_payment_code' => '12010',
        'saf_t_event_code' => '13019',
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
    ]);

    $charge = ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'pending',
        'payment_method' => 'deferred',
        'paid' => false,
        'amount' => 10000,
        'metadata' => [
            'deferred_payment' => true,
            'purchase_contains_tickets' => true,
            'purchase_ticket_reference' => 'BK-DEFERRED-TICKET',
            'items' => [
                [
                    'id' => 'ticket_line_1',
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'metadata' => [
                        'merano_booking_id' => 12,
                        'merano_booking_number' => 'BK-DEFERRED-TICKET',
                    ],
                ],
            ],
            'total' => 10000,
        ],
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/purchases/{$charge->id}")
        ->assertOk()
        ->assertJsonPath('purchase.purchase_metadata.purchase_contains_tickets', true);

    $this->postJson("/api/purchases/{$charge->id}/revise-deferred", [
        'pos_session_id' => $session->id,
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
            ],
            'total' => 10000,
            'currency' => 'nok',
        ],
    ])->assertOk();

    $this->getJson("/api/purchases/{$charge->id}")
        ->assertOk()
        ->assertJsonMissingPath('purchase.purchase_metadata.purchase_contains_tickets')
        ->assertJsonMissingPath('purchase.purchase_metadata.purchase_ticket_reference');
});
