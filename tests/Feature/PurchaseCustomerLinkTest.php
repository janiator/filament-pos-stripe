<?php

use App\Models\ConnectedCharge;
use App\Models\ConnectedCustomer;
use App\Models\ConnectedProduct;
use App\Models\PaymentMethod;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, store: Store, session: PosSession, customer: ConnectedCustomer, paymentMethod: PaymentMethod, product: ConnectedProduct}
 */
function purchaseCustomerLinkSetup(string $provider = 'cash', string $code = 'cash'): array
{
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_custlink_'.uniqid()]);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $posDevice = PosDevice::factory()->create(['store_id' => $store->id, 'cash_drawer_enabled' => true]);
    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $posDevice->id,
        'user_id' => $user->id,
        'status' => 'open',
    ]);

    $customer = ConnectedCustomer::create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_customer_id' => 'cus_link_'.uniqid(),
        'name' => 'Kari Nordmann',
        'email' => 'kari@example.com',
    ]);

    $paymentMethod = PaymentMethod::create([
        'store_id' => $store->id,
        'name' => ucfirst($code),
        'code' => $code,
        'provider' => $provider,
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'minimum_amount_kroner' => null,
        'saf_t_payment_code' => '12001',
        'saf_t_event_code' => '13017',
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
    ]);

    return compact('user', 'store', 'session', 'customer', 'paymentMethod', 'product');
}

test('cash purchase links customer from cart', function () {
    extract(purchaseCustomerLinkSetup());

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'cash',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
            ],
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'subtotal' => 10000,
            'total_discounts' => 0,
            'total_tax' => 2000,
            'total' => 10000,
            'currency' => 'nok',
        ],
        'metadata' => [],
    ]);

    $response->assertCreated();

    $charge = ConnectedCharge::findOrFail($response->json('data.charge.id'));

    expect($charge->stripe_customer_id)->toBe($customer->stripe_customer_id)
        ->and($charge->customer?->id)->toBe($customer->id)
        ->and($charge->metadata['customer_id'])->toBe($customer->id);
});

test('stripe purchase links customer when webhook charge already exists', function () {
    extract(purchaseCustomerLinkSetup(provider: 'stripe', code: 'card'));

    $paymentIntentId = 'pi_custlink_'.uniqid();
    $stripeChargeId = 'ch_custlink_'.uniqid();

    ConnectedCharge::factory()->create([
        'stripe_charge_id' => $stripeChargeId,
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_payment_intent_id' => $paymentIntentId,
        'stripe_customer_id' => null,
        'pos_session_id' => null,
        'status' => 'succeeded',
        'paid' => true,
        'amount' => 10000,
    ]);

    $paymentIntent = PaymentIntent::constructFrom([
        'id' => $paymentIntentId,
        'status' => 'succeeded',
        'currency' => 'nok',
        'customer' => null,
        'latest_charge' => $stripeChargeId,
        'charges' => [
            'data' => [
                ['id' => $stripeChargeId, 'captured' => true],
            ],
        ],
    ]);

    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('retrieve')
        ->once()
        ->andReturn($paymentIntent);

    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->paymentIntents = $mockPaymentIntents;

    $service = app(PurchaseService::class);
    $reflection = new ReflectionClass($service);
    $stripeProperty = $reflection->getProperty('stripeClient');
    $stripeProperty->setAccessible(true);
    $stripeProperty->setValue($service, $mockStripe);

    $result = $service->processPurchase(
        $session,
        $paymentMethod,
        [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
            ],
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'subtotal' => 10000,
            'total_discounts' => 0,
            'total_tax' => 2000,
            'total' => 10000,
            'currency' => 'nok',
        ],
        ['payment_intent_id' => $paymentIntentId]
    );

    $charge = $result['charge']->fresh();

    expect($charge->stripe_customer_id)->toBe($customer->stripe_customer_id)
        ->and($charge->customer?->id)->toBe($customer->id)
        ->and($charge->metadata['customer_id'])->toBe($customer->id)
        ->and($charge->pos_session_id)->toBe($session->id);
});
