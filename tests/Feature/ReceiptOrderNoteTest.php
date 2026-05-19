<?php

use App\Models\ConnectedCharge;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use App\Services\ReceiptGenerationService;
use App\Services\ReceiptTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sales receipt stores order_note and renders it in ePOS xml', function () {
    $store = Store::factory()->create();
    $device = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create(['pos_device_id' => $device->id]);
    $charge = ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'paid' => true,
        'paid_at' => now(),
        'metadata' => [
            'note' => 'Kunden ønsker pose',
            'items' => [[
                'name' => 'Vare',
                'product_name' => 'Vare',
                'quantity' => 1,
                'unit_price' => '50,00',
                'line_total' => '50,00',
            ]],
            'subtotal' => 5000,
            'total_tax' => 1000,
            'total_discounts' => 0,
        ],
    ]);

    $receipt = app(ReceiptGenerationService::class)->generateSalesReceipt($charge, $session);

    expect($receipt->receipt_type)->toBe('sales')
        ->and($receipt->receipt_data['order_note'])->toBe('Kunden ønsker pose');

    $xml = app(ReceiptTemplateService::class)->renderReceipt($receipt);

    expect($xml)->toContain('Notat')
        ->and($xml)->toContain('Kunden ønsker pose');
});

test('delivery receipt stores order_note and renders it in ePOS xml', function () {
    $store = Store::factory()->create();
    $device = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create(['pos_device_id' => $device->id]);
    $charge = ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'pending',
        'paid' => false,
        'paid_at' => null,
        'metadata' => [
            'note' => 'Hentes fredag',
            'items' => [[
                'name' => 'Tjeneste',
                'product_name' => 'Tjeneste',
                'quantity' => 1,
                'unit_price' => '100,00',
                'line_total' => '100,00',
            ]],
            'subtotal' => 10000,
            'total_tax' => 2500,
            'total_discounts' => 0,
        ],
    ]);

    $receipt = app(ReceiptGenerationService::class)->generateDeliveryReceipt($charge, $session);

    expect($receipt->receipt_type)->toBe('delivery')
        ->and($receipt->receipt_data['order_note'])->toBe('Hentes fredag');

    $xml = app(ReceiptTemplateService::class)->renderReceipt($receipt);

    expect($xml)->toContain('Notat')
        ->and($xml)->toContain('Hentes fredag');
});

test('delivery receipt with deferred payment method renders and saves xml', function () {
    $store = Store::factory()->create();
    $device = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create(['pos_device_id' => $device->id]);
    $charge = ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'pending',
        'paid' => false,
        'paid_at' => null,
        'payment_method' => 'deferred',
        'metadata' => [
            'deferred_payment' => true,
            'items' => [[
                'name' => 'Tjeneste',
                'product_name' => 'Tjeneste',
                'quantity' => 1,
                'unit_price' => '100,00',
                'line_total' => '100,00',
            ]],
            'subtotal' => 10000,
            'total_tax' => 2500,
            'total_discounts' => 0,
        ],
    ]);

    $receipt = app(ReceiptGenerationService::class)->generateDeliveryReceipt($charge, $session);

    expect($receipt->receipt_type)->toBe('delivery')
        ->and($receipt->receipt_data['xml'] ?? null)->not->toBeEmpty()
        ->and($receipt->receipt_data['xml'])->toContain('Ordrebekreftelse');
});
