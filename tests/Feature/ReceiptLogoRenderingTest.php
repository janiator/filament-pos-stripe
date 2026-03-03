<?php

namespace Tests\Feature;

use App\Models\ConnectedCharge;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Receipt;
use App\Models\Store;
use App\Models\User;
use App\Services\ReceiptTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptLogoRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function createReceiptForStore(Store $store, array $receiptData = []): Receipt
    {
        $user = User::factory()->create();
        $user->stores()->attach($store);

        $device = PosDevice::factory()->create(['store_id' => $store->id]);

        $session = PosSession::factory()->create([
            'store_id' => $store->id,
            'pos_device_id' => $device->id,
            'user_id' => $user->id,
            'status' => 'open',
        ]);

        $charge = ConnectedCharge::factory()->create([
            'stripe_account_id' => $store->stripe_account_id,
            'pos_session_id' => $session->id,
            'amount' => 10000,
            'currency' => 'nok',
            'status' => 'succeeded',
            'paid' => true,
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        $defaultReceiptData = [
            'items' => [
                [
                    'name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => '100,00',
                    'line_total' => '100,00',
                    'price_amount' => 10000,
                ],
            ],
            'total' => 100,
            'date' => now()->format('Y-m-d H:i:s'),
        ];

        return Receipt::factory()->create(array_merge([
            'store_id' => $store->id,
            'pos_session_id' => $session->id,
            'charge_id' => $charge->id,
            'user_id' => $user->id,
            'receipt_type' => 'sales',
            'receipt_number' => Receipt::generateReceiptNumber($store->id, 'sales'),
            'receipt_data' => array_merge($defaultReceiptData, $receiptData['receipt_data'] ?? []),
        ], $receiptData));
    }

    public function test_rendered_receipt_xml_uses_logo_fallback_when_store_has_no_logo(): void
    {
        $store = Store::factory()->create([
            'logo_path' => null,
            'stripe_account_id' => 'acct_test_'.uniqid(),
        ]);

        $receipt = $this->createReceiptForStore($store);
        $receipt->load(['store', 'charge', 'posSession', 'user']);

        $templateService = app(ReceiptTemplateService::class);
        $xml = $templateService->renderReceipt($receipt);

        $this->assertStringContainsString('<logo key1="34" key2="48"/>', $xml);
        $this->assertStringNotContainsString('<image width="', $xml);
    }

    public function test_rendered_receipt_xml_includes_store_logo_image_when_store_has_logo(): void
    {
        Storage::fake('public');

        $logoPath = 'store-logos/test-logo.png';
        $minimalPng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        $this->assertNotFalse($minimalPng, 'Minimal 1x1 PNG decode');
        Storage::disk('public')->put($logoPath, $minimalPng);

        $store = Store::factory()->create([
            'logo_path' => $logoPath,
            'stripe_account_id' => 'acct_test_'.uniqid(),
        ]);

        $receipt = $this->createReceiptForStore($store);
        $receipt->load(['store', 'charge', 'posSession', 'user']);

        $templateService = app(ReceiptTemplateService::class);
        $xml = $templateService->renderReceipt($receipt);

        $this->assertStringContainsString('<image width="', $xml);
        $this->assertStringContainsString('height="', $xml);
        $this->assertStringNotContainsString('<logo key1="34" key2="48"/>', $xml);
    }

    public function test_rendered_receipt_xml_uses_logo_fallback_when_logo_path_exists_but_file_missing(): void
    {
        Storage::fake('public');

        $store = Store::factory()->create([
            'logo_path' => 'store-logos/missing-logo.png',
            'stripe_account_id' => 'acct_test_'.uniqid(),
        ]);

        $receipt = $this->createReceiptForStore($store);
        $receipt->load(['store', 'charge', 'posSession', 'user']);

        $templateService = app(ReceiptTemplateService::class);
        $xml = $templateService->renderReceipt($receipt);

        $this->assertStringContainsString('<logo key1="34" key2="48"/>', $xml);
        $this->assertStringNotContainsString('<image width="', $xml);
    }

    /** Copy receipt must show same line items and transaction ID as original (not "Cash payment" or "N/A") */
    public function test_copy_receipt_xml_shows_original_items_and_transaction_id(): void
    {
        $store = Store::factory()->create([
            'logo_path' => null,
            'stripe_account_id' => 'acct_test_'.uniqid(),
        ]);

        $salesReceiptData = [
            'receipt_data' => [
                'items' => [
                    ['name' => '10. august', 'quantity' => 1, 'unit_price' => '100,00', 'line_total' => '100,00', 'price_amount' => 10000],
                    ['name' => '3 pack callaway supersoft hvit', 'quantity' => 1, 'unit_price' => '99,00', 'line_total' => '99,00', 'price_amount' => 9900],
                ],
                'total' => 199,
                'date' => now()->format('Y-m-d H:i:s'),
                'transaction_id' => 'ch_142',
            ],
        ];

        $salesReceipt = $this->createReceiptForStore($store, $salesReceiptData);
        $salesReceipt->load(['store', 'charge', 'posSession', 'user']);

        // Copy with same charge_id but no original_receipt_id (simulates POS receipts/generate with type=copy)
        $copyReceipt = Receipt::factory()->create([
            'store_id' => $store->id,
            'pos_session_id' => $salesReceipt->pos_session_id,
            'charge_id' => $salesReceipt->charge_id,
            'user_id' => $salesReceipt->user_id,
            'receipt_type' => 'copy',
            'original_receipt_id' => null,
            'receipt_number' => Receipt::generateReceiptNumber($store->id, 'copy'),
            'receipt_data' => [
                'store' => ['name' => $store->name],
                'charge_id' => $salesReceipt->charge->stripe_charge_id,
                'amount' => 199,
            ],
        ]);
        $copyReceipt->load(['store', 'charge', 'posSession', 'user']);

        $templateService = app(ReceiptTemplateService::class);
        $xml = $templateService->renderReceipt($copyReceipt);

        $this->assertStringContainsString('10. august', $xml, 'Copy receipt XML must contain original line items');
        $this->assertStringContainsString('callaway supersoft', $xml, 'Copy receipt XML must contain original line items');
        $this->assertStringNotContainsString('Cash payment', $xml, 'Copy receipt must not show fallback "Cash payment"');
        $this->assertStringNotContainsString('Transaksjons-ID: N/A', $xml, 'Copy receipt must not show N/A for transaction id');
        $this->assertMatchesRegularExpression('/Transaksjons-ID: .+/', $xml, 'Copy receipt must show a transaction id');
    }
}
