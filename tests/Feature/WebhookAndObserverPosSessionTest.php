<?php

use App\Actions\Webhooks\HandleChargeWebhook;
use App\Actions\Webhooks\HandlePaymentMethodWebhook;
use App\Models\ConnectedCharge;
use App\Models\ConnectedPaymentMethod;
use App\Models\PosEvent;
use App\Models\PosSession;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Charge;

uses(RefreshDatabase::class);

/**
 * Build a Stripe Charge object from array for webhook tests.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeStripeChargeForWebhook(array $overrides = []): Charge
{
    $defaults = [
        'id' => 'ch_'.uniqid(),
        'payment_intent' => 'pi_'.uniqid(),
        'customer' => null,
        'amount' => 5000,
        'amount_refunded' => 0,
        'currency' => 'nok',
        'status' => 'succeeded',
        'description' => null,
        'failure_code' => null,
        'failure_message' => null,
        'captured' => true,
        'refunded' => false,
        'paid' => true,
        'created' => time(),
        'metadata' => ['session_number' => '000001'],
        'outcome' => ['network_status' => 'approved'],
        'on_behalf_of' => null,
        'application_fee_amount' => null,
        'payment_method_details' => ['type' => 'card'],
    ];

    return Charge::constructFrom(array_merge($defaults, $overrides));
}

it('merges webhook into existing charge by payment_intent_id and preserves pos_session_id', function () {
    $store = Store::factory()->create(['stripe_account_id' => 'acct_'.uniqid()]);
    $session = PosSession::factory()->create(['store_id' => $store->id]);
    $paymentIntentId = 'pi_'.uniqid();
    $stripeChargeId = 'ch_'.uniqid();

    $existing = ConnectedCharge::factory()->create([
        'stripe_charge_id' => $stripeChargeId,
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_payment_intent_id' => $paymentIntentId,
        'pos_session_id' => $session->id,
        'transaction_code' => '11001',
        'payment_code' => '12001',
        'tip_amount' => 100,
        'article_group_code' => '04003',
        'amount' => 3000,
        'status' => 'succeeded',
        'paid' => true,
    ]);

    $stripeCharge = makeStripeChargeForWebhook([
        'id' => $stripeChargeId,
        'payment_intent' => $paymentIntentId,
        'amount' => 6000,
        'on_behalf_of' => $store->stripe_account_id,
    ]);

    app(HandleChargeWebhook::class)->handle(
        $stripeCharge,
        'charge.succeeded',
        $store->stripe_account_id
    );

    $existing->refresh();
    expect($existing->amount)->toBe(6000)
        ->and($existing->pos_session_id)->toBe($session->id)
        ->and($existing->transaction_code)->toBe('11001')
        ->and($existing->payment_code)->toBe('12001')
        ->and($existing->tip_amount)->toBe(100)
        ->and($existing->article_group_code)->toBe('04003');

    expect(ConnectedCharge::where('stripe_payment_intent_id', $paymentIntentId)->count())->toBe(1);
});

it('does not create POS events when ConnectedCharge has null pos_session_id', function () {
    $store = Store::factory()->create(['stripe_account_id' => 'acct_'.uniqid()]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => null,
        'status' => 'succeeded',
        'paid' => true,
        'payment_method' => 'card',
    ]);

    expect(PosEvent::where('event_code', PosEvent::EVENT_SALES_RECEIPT)->count())->toBe(0);
});

it('creates POS events when ConnectedCharge has pos_session_id', function () {
    $store = Store::factory()->create(['stripe_account_id' => 'acct_'.uniqid()]);
    $session = PosSession::factory()->create(['store_id' => $store->id]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'paid' => true,
        'payment_method' => 'card',
    ]);

    expect(PosEvent::where('event_code', PosEvent::EVENT_SALES_RECEIPT)->count())->toBe(1);
});

it('ignores charge refund update events when webhook object is not a charge', function () {
    $store = Store::factory()->create(['stripe_account_id' => 'acct_'.uniqid()]);

    $secret = 'whsec_test_refund_event';
    config()->set('cashierconnect.webhook.secret', $secret);

    $payload = json_encode([
        'id' => 'evt_'.uniqid(),
        'object' => 'event',
        'type' => 'charge.refund.updated',
        'account' => $store->stripe_account_id,
        'data' => [
            'object' => [
                'id' => 're_'.uniqid(),
                'object' => 'refund',
                'charge' => 'ch_'.uniqid(),
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();
    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);
    $signatureHeader = "t={$timestamp},v1={$signature}";

    $response = $this->withHeader('Stripe-Signature', $signatureHeader)
        ->postJson('/api/connectWebhook', json_decode($payload, true, 512, JSON_THROW_ON_ERROR));

    $response->assertOk()
        ->assertJsonPath('event_type', 'charge.refund.updated')
        ->assertJsonPath('processed', false)
        ->assertJsonPath('errors', []);
});

it('does not overwrite existing payment method customer with null value', function () {
    $store = Store::factory()->create(['stripe_account_id' => 'acct_'.uniqid()]);

    $existing = ConnectedPaymentMethod::query()->create([
        'stripe_payment_method_id' => 'pm_'.uniqid(),
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_customer_id' => 'cus_existing_123',
        'type' => 'card',
        'card_brand' => 'visa',
        'card_last4' => '4242',
        'card_exp_month' => 1,
        'card_exp_year' => 2030,
    ]);

    $paymentMethod = \Stripe\PaymentMethod::constructFrom([
        'id' => $existing->stripe_payment_method_id,
        'customer' => null,
        'type' => 'card',
        'card' => [
            'brand' => 'mastercard',
            'last4' => '4444',
            'exp_month' => 12,
            'exp_year' => 2032,
        ],
        'metadata' => [],
    ]);

    app(HandlePaymentMethodWebhook::class)->handle(
        $paymentMethod,
        'payment_method.updated',
        $store->stripe_account_id
    );

    $existing->refresh();

    expect($existing->stripe_customer_id)->toBe('cus_existing_123')
        ->and($existing->card_brand)->toBe('mastercard')
        ->and($existing->card_last4)->toBe('4444');
});
