<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PosEvent;
use App\Models\Receipt;
use App\Services\SafTCodeMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class PosSystemIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Store $store;
    protected PosDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and store
        $this->user = User::factory()->create();
        $this->store = Store::factory()->create([
            'stripe_account_id' => 'acct_test_' . $this->faker->uuid,
        ]);
        $this->user->stores()->attach($this->store);
        $this->user->setCurrentStore($this->store);

        // Create POS device
        $this->device = PosDevice::factory()->create([
            'store_id' => $this->store->id,
            'device_name' => 'Test POS Device',
        ]);

        // Authenticate user
        Sanctum::actingAs($this->user, ['*']);
    }

    /**
     * Test complete POS workflow from session open to close
     */
    public function test_complete_pos_workflow(): void
    {
        // Step 1: Open a POS session
        $response = $this->postJson('/api/pos-sessions/open', [
            'pos_device_id' => $this->device->id,
            'opening_balance' => 10000, // 100.00 NOK in cents
            'opening_notes' => 'Morning shift',
        ]);

        $response->assertStatus(201);
        $sessionData = $response->json('session');
        $sessionId = $sessionData['id'];

        // Verify session was created
        $session = PosSession::find($sessionId);
        $this->assertNotNull($session);
        $this->assertEquals('open', $session->status);
        $this->assertEquals(10000, $session->opening_balance);

        // Verify session opened event was logged
        $this->assertDatabaseHas('pos_events', [
            'pos_session_id' => $sessionId,
            'event_code' => PosEvent::EVENT_SESSION_OPENED,
            'event_type' => 'session',
        ]);

        // Step 2: Create a product
        $product = ConnectedProduct::factory()->create([
            'stripe_account_id' => $this->store->stripe_account_id,
            'name' => 'Test Product',
            'article_group_code' => '04003', // Varesalg
            'product_code' => 'PLU001',
        ]);

        // Step 3: Create a charge (transaction)
        $charge = ConnectedCharge::factory()->create([
            'stripe_account_id' => $this->store->stripe_account_id,
            'pos_session_id' => $sessionId,
            'amount' => 25000, // 250.00 NOK
            'currency' => 'nok',
            'status' => 'succeeded',
            'paid' => true,
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        // Verify SAF-T codes were auto-mapped
        $charge->refresh();
        $this->assertNotNull($charge->payment_code);
        $this->assertNotNull($charge->transaction_code);
        $this->assertEquals('12002', $charge->payment_code); // Debit card
        $this->assertEquals('11002', $charge->transaction_code); // Credit sale

        // Verify sales receipt event was logged
        $this->assertDatabaseHas('pos_events', [
            'pos_session_id' => $sessionId,
            'related_charge_id' => $charge->id,
            'event_code' => PosEvent::EVENT_SALES_RECEIPT,
            'event_type' => 'transaction',
        ]);

        // Verify payment method event was logged
        $this->assertDatabaseHas('pos_events', [
            'pos_session_id' => $sessionId,
            'related_charge_id' => $charge->id,
            'event_code' => PosEvent::EVENT_CARD_PAYMENT,
            'event_type' => 'payment',
        ]);

        // Step 4: Generate X-report
        $xReportResponse = $this->postJson("/api/pos-sessions/{$sessionId}/x-report");
        $xReportResponse->assertStatus(200);
        $xReport = $xReportResponse->json('report');
        
        $this->assertEquals(1, $xReport['transactions_count']);
        $this->assertEquals(25000, $xReport['total_amount']);
        $this->assertEquals(0, $xReport['cash_amount']);
        $this->assertEquals(25000, $xReport['card_amount']);

        // Verify X-report event was logged
        $this->assertDatabaseHas('pos_events', [
            'pos_session_id' => $sessionId,
            'event_code' => PosEvent::EVENT_X_REPORT,
            'event_type' => 'report',
        ]);

        // Step 5: Generate receipt
        $receiptResponse = $this->postJson('/api/receipts/generate', [
            'charge_id' => $charge->id,
            'pos_session_id' => $sessionId,
        ]);
        $receiptResponse->assertStatus(201);
        $receiptData = $receiptResponse->json('receipt');
        $receiptId = $receiptData['id'];

        $receipt = Receipt::find($receiptId);
        $this->assertNotNull($receipt);
        $this->assertEquals('sales', $receipt->receipt_type);
        $this->assertEquals($charge->id, $receipt->charge_id);

        // Step 6: Create a cash transaction
        $cashCharge = ConnectedCharge::factory()->create([
            'stripe_account_id' => $this->store->stripe_account_id,
            'pos_session_id' => $sessionId,
            'amount' => 15000, // 150.00 NOK
            'currency' => 'nok',
            'status' => 'succeeded',
            'paid' => true,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        // Verify cash payment event was logged
        $this->assertDatabaseHas('pos_events', [
            'pos_session_id' => $sessionId,
            'related_charge_id' => $cashCharge->id,
            'event_code' => PosEvent::EVENT_CASH_PAYMENT,
            'event_type' => 'payment',
        ]);

        // Step 7: Generate Z-report (closes session)
        $zReportResponse = $this->postJson("/api/pos-sessions/{$sessionId}/z-report", [
            'actual_cash' => 15000,
            'closing_notes' => 'End of shift',
        ]);
        $zReportResponse->assertStatus(200);
        $zReport = $zReportResponse->json('report');

        $this->assertEquals(2, $zReport['transactions_count']);
        $this->assertEquals(40000, $zReport['total_amount']);
        $this->assertEquals(15000, $zReport['cash_amount']);
        $this->assertEquals(25000, $zReport['card_amount']);

        // Verify session was closed
        $session->refresh();
        $this->assertEquals('closed', $session->status);
        $this->assertNotNull($session->closed_at);
        $this->assertEquals(15000, $session->expected_cash);
        $this->assertEquals(15000, $session->actual_cash);
        $this->assertEquals(0, $session->cash_difference);

        // Verify Z-report and session closed events were logged
        $this->assertDatabaseHas('pos_events', [
            'pos_session_id' => $sessionId,
            'event_code' => PosEvent::EVENT_Z_REPORT,
            'event_type' => 'report',
        ]);

        $this->assertDatabaseHas('pos_events', [
            'pos_session_id' => $sessionId,
            'event_code' => PosEvent::EVENT_SESSION_CLOSED,
            'event_type' => 'session',
        ]);
    }

    /**
     * Test return/refund workflow
     */
    public function test_return_refund_workflow(): void
    {
        // Create session
        $session = PosSession::factory()->create([
            'store_id' => $this->store->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);

        // Create original charge
        $originalCharge = ConnectedCharge::factory()->create([
            'stripe_account_id' => $this->store->stripe_account_id,
            'pos_session_id' => $session->id,
            'amount' => 10000,
            'status' => 'succeeded',
            'paid' => true,
            'payment_method' => 'card',
        ]);

        // Create receipt for original charge
        $originalReceipt = Receipt::factory()->create([
            'store_id' => $this->store->id,
            'pos_session_id' => $session->id,
            'charge_id' => $originalCharge->id,
            'receipt_type' => 'sales',
        ]);

        // Process refund
        $originalCharge->update([
            'refunded' => true,
            'amount_refunded' => 10000,
        ]);

        // Verify return receipt event was logged
        $this->assertDatabaseHas('pos_events', [
            'pos_session_id' => $session->id,
            'related_charge_id' => $originalCharge->id,
            'event_code' => PosEvent::EVENT_RETURN_RECEIPT,
            'event_type' => 'transaction',
        ]);
    }

    /**
     * Test SAF-T code mapping
     */
    public function test_saf_t_code_mapping(): void
    {
        // Test payment code mapping
        $this->assertEquals('12001', SafTCodeMapper::mapPaymentMethodToCode('cash'));
        $this->assertEquals('12002', SafTCodeMapper::mapPaymentMethodToCode('card'));
        $this->assertEquals('12011', SafTCodeMapper::mapPaymentMethodToCode('mobile'));
        $this->assertEquals('12999', SafTCodeMapper::mapPaymentMethodToCode('unknown'));

        // Test transaction code mapping
        $cashCharge = ConnectedCharge::factory()->make([
            'payment_method' => 'cash',
            'refunded' => false,
        ]);
        $this->assertEquals('11001', SafTCodeMapper::mapTransactionToCode($cashCharge));

        $cardCharge = ConnectedCharge::factory()->make([
            'payment_method' => 'card',
            'refunded' => false,
        ]);
        $this->assertEquals('11002', SafTCodeMapper::mapTransactionToCode($cardCharge));

        $refundCharge = ConnectedCharge::factory()->make([
            'refunded' => true,
            'amount_refunded' => 1000,
        ]);
        $this->assertEquals('11006', SafTCodeMapper::mapTransactionToCode($refundCharge));
    }

    /**
     * Test event listing and filtering
     */
    public function test_event_listing_and_filtering(): void
    {
        $session = PosSession::factory()->create([
            'store_id' => $this->store->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->user->id,
        ]);

        // Create various events
        PosEvent::factory()->create([
            'store_id' => $this->store->id,
            'pos_session_id' => $session->id,
            'event_code' => PosEvent::EVENT_SESSION_OPENED,
            'event_type' => 'session',
        ]);

        PosEvent::factory()->create([
            'store_id' => $this->store->id,
            'pos_session_id' => $session->id,
            'event_code' => PosEvent::EVENT_CASH_PAYMENT,
            'event_type' => 'payment',
        ]);

        // Test filtering by event type
        $response = $this->getJson('/api/pos-events?event_type=session');
        $response->assertStatus(200);
        $events = $response->json('events');
        $this->assertCount(1, $events);
        $this->assertEquals(PosEvent::EVENT_SESSION_OPENED, $events[0]['event_code']);

        // Test filtering by event code
        $response = $this->getJson('/api/pos-events?event_code=' . PosEvent::EVENT_CASH_PAYMENT);
        $response->assertStatus(200);
        $events = $response->json('events');
        $this->assertCount(1, $events);
        $this->assertEquals(PosEvent::EVENT_CASH_PAYMENT, $events[0]['event_code']);
    }

    /**
     * Test receipt generation and management
     */
    public function test_receipt_generation_and_management(): void
    {
        $session = PosSession::factory()->create([
            'store_id' => $this->store->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->user->id,
        ]);

        $charge = ConnectedCharge::factory()->create([
            'stripe_account_id' => $this->store->stripe_account_id,
            'pos_session_id' => $session->id,
            'amount' => 5000,
            'status' => 'succeeded',
            'paid' => true,
        ]);

        // Generate receipt
        $response = $this->postJson('/api/receipts/generate', [
            'charge_id' => $charge->id,
            'pos_session_id' => $session->id,
        ]);
        $response->assertStatus(201);
        $receiptId = $response->json('receipt.id');

        // Mark as printed
        $response = $this->postJson("/api/receipts/{$receiptId}/mark-printed");
        $response->assertStatus(200);
        $this->assertTrue($response->json('receipt.printed'));

        // Reprint
        $response = $this->postJson("/api/receipts/{$receiptId}/reprint");
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('receipt.reprint_count'));
    }

    /**
     * Test current session retrieval
     */
    public function test_current_session_retrieval(): void
    {
        // Create open session
        $session = PosSession::factory()->create([
            'store_id' => $this->store->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/pos-sessions/current?pos_device_id=' . $this->device->id);
        $response->assertStatus(200);
        $this->assertEquals($session->id, $response->json('session.id'));
    }

    /**
     * Test SAF-T export includes all events and codes
     */
    public function test_saf_t_export_includes_all_data(): void
    {
        $session = PosSession::factory()->create([
            'store_id' => $this->store->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->user->id,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $charge = ConnectedCharge::factory()->create([
            'stripe_account_id' => $this->store->stripe_account_id,
            'pos_session_id' => $session->id,
            'amount' => 10000,
            'payment_method' => 'cash',
            'payment_code' => '12001',
            'transaction_code' => '11001',
            'article_group_code' => '04003',
            'tip_amount' => 500,
            'status' => 'succeeded',
            'paid' => true,
        ]);

        // Generate SAF-T
        $response = $this->postJson('/api/saf-t/generate', [
            'from_date' => now()->subDay()->format('Y-m-d'),
            'to_date' => now()->format('Y-m-d'),
        ]);
        $response->assertStatus(200);

        $xml = $response->json('xml');
        $this->assertStringContainsString('12001', $xml); // Payment code
        $this->assertStringContainsString('11001', $xml); // Transaction code
        $this->assertStringContainsString('04003', $xml); // Article group code
        $this->assertStringContainsString('10001', $xml); // Tip code
    }
}
