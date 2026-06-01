<?php

use App\Models\ConnectedCharge;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
use App\Support\MeranoTicketPurchaseMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

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
