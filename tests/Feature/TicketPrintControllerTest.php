<?php

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\ReceiptPrinter;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_'.str_replace('-', '', fake()->uuid()),
    ]);
    $this->user = User::factory()->create();
    $this->user->stores()->attach($this->store);
    $this->user->setCurrentStore($this->store);
    Sanctum::actingAs($this->user, ['*']);

    $this->printer = ReceiptPrinter::create([
        'store_id' => $this->store->id,
        'name' => 'Front Desk Printer',
        'printer_type' => 'epson',
        'connection_type' => 'network',
        'ip_address' => '127.0.0.1',
        'port' => 9100,
        'device_id' => 'local_printer',
        'is_active' => true,
    ]);
});

test('free ticket endpoint renders xml with repeated tickets and optional sections', function () {
    $response = $this->post('/api/receipts/print-freeticket', [
        'printer_id' => $this->printer->id,
        'date' => '10.03.2026',
        'place' => 'Oslo',
        'code' => 'FREE-ABC',
        'amount' => 2,
        'discount' => 'Student',
        'applies_to' => 'Barn',
        'max_tickets' => 3,
    ]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

    $xml = $response->getContent();

    expect(substr_count($xml, 'GRATISBILLETT'))->toBe(2);
    expect($xml)->toContain('FREE-ABC');
    expect($xml)->toContain('Oslo');
    expect($xml)->toContain('Rabatt: Student');
    expect($xml)->toContain('Gjelder for: Barn');
    expect($xml)->toContain('Maks billetter: 3');
    expect($xml)->not->toContain('DISCOUNTLINE-START');
    expect($xml)->not->toContain('APPLIESTO-START');
});

test('booking ticket endpoint requires the merano booking addon', function () {
    $response = $this->postJson('/api/receipts/print-ticket', [
        'printer_id' => $this->printer->id,
        'order_number' => 'BK-100',
        'date' => '10.03.2026 19:00',
        'place' => 'Oslo Spektrum',
        'heading' => 'Inngangsbillett',
        'amount_paid' => 10000,
        'tickets' => [
            [
                'category' => 'Tribune',
                'section' => 'A',
                'row' => '3',
                'seat' => '12',
                'entrance' => 'Nord',
            ],
        ],
    ]);

    $response->assertForbidden();
    $response->assertJsonPath('message', 'Merano booking is not enabled for this store.');
});

test('booking ticket endpoint renders losje tickets without tribune instructions', function () {
    Addon::factory()->for($this->store)->create([
        'type' => AddonType::MeranoBooking,
        'is_active' => true,
    ]);

    $response = $this->post('/api/receipts/print-ticket', [
        'printer_id' => $this->printer->id,
        'order_number' => 'BK-100',
        'date' => '10.03.2026 19:00',
        'place' => 'Oslo Spektrum',
        'heading' => 'Inngangsbillett',
        'amount_paid' => 12500,
        'tickets' => [
            [
                'category' => 'VIP Losje',
                'section' => 'VIP',
                'row' => '1',
                'seat' => 'A-1',
                'entrance' => 'Losjeinngang',
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

    $xml = $response->getContent();

    expect($xml)->toContain('Inngangsbillett');
    expect($xml)->toContain('Oslo Spektrum');
    expect($xml)->toContain('Ordre: BK-100');
    expect($xml)->toContain('Sete: A-1');
    expect($xml)->toContain('Losjebillett. Folg skilting til losjeinngangen.');
    expect($xml)->not->toContain('Folg skilting til tribuneinngangen.');
    expect($xml)->not->toContain('TRIBUNE-START');
    expect($xml)->not->toContain('LOSJE-START');
});

test('booking ticket endpoint can use explicit per-ticket prices', function () {
    Addon::factory()->for($this->store)->create([
        'type' => AddonType::MeranoBooking,
        'is_active' => true,
    ]);

    $response = $this->post('/api/receipts/print-ticket', [
        'printer_id' => $this->printer->id,
        'order_number' => 'BK-101',
        'date' => '10.03.2026 21:00',
        'place' => 'Oslo Spektrum',
        'heading' => 'Billett',
        'tickets' => [
            [
                'category' => 'Tribune',
                'section' => 'B',
                'row' => '4',
                'seat' => '13',
                'entrance' => 'Sør',
                'ticket_price' => 2500,
            ],
        ],
    ]);

    $response->assertOk();
    expect($response->getContent())->toContain('Pris: 25,00 NOK');
});
