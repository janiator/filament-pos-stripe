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
}
