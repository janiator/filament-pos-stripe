<?php

use App\Actions\ConnectedCharges\SyncConnectedChargesFromStripe;
use App\Models\ConnectedCharge;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Charge;
use Stripe\Collection;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

afterEach(function () {
    Mockery::close();
});

function makeStripeChargeForConnectedChargeSync(string $chargeId, string $onBehalfOfAccountId): Charge
{
    return Charge::constructFrom([
        'id' => $chargeId,
        'customer' => null,
        'payment_intent' => 'pi_'.uniqid(),
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
        'metadata' => [],
        'outcome' => null,
        'on_behalf_of' => $onBehalfOfAccountId,
        'application_fee_amount' => null,
        'payment_method_details' => ['type' => 'card'],
    ]);
}

it('sync upserts by stripe_charge_id so a second run does not insert a duplicate', function () {
    $accountId = 'acct_sync_'.uniqid();
    $store = Store::factory()->create(['stripe_account_id' => $accountId]);
    $chargeId = 'ch_sync_'.uniqid();

    $stripeCharge = makeStripeChargeForConnectedChargeSync($chargeId, $accountId);
    $collection = Collection::constructFrom([
        'object' => 'list',
        'data' => [$stripeCharge],
        'has_more' => false,
        'url' => '/v1/charges',
    ]);

    $mockCharges = Mockery::mock();
    $mockCharges->shouldReceive('all')
        ->twice()
        ->andReturn($collection);

    $mockStripe = Mockery::mock('overload:'.StripeClient::class);
    $mockStripe->shouldReceive('__construct')
        ->twice()
        ->andReturnSelf();
    $mockStripe->charges = $mockCharges;

    $action = new SyncConnectedChargesFromStripe;

    $result1 = $action($store);
    $result2 = $action($store);

    expect(ConnectedCharge::where('stripe_charge_id', $chargeId)->count())->toBe(1)
        ->and($result1['created'])->toBe(1)
        ->and($result1['updated'])->toBe(0)
        ->and($result2['created'])->toBe(0)
        ->and($result2['updated'])->toBe(1);
});
