<?php

declare(strict_types=1);

use App\Actions\ConnectedPrices\SyncProductPrice;
use App\Models\ConnectedProduct;
use Stripe\StripeClient;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function () {
    Mockery::close();
});

/**
 * Stripe rejects archiving a price that is still the product's default_price.
 * updateStripeProductDefaultPrice() must call the Stripe Products API so the product
 * references the new price before any price is archived (see SyncProductPrice::__invoke).
 */
it('updates the Stripe product default price via the Products API', function () {
    config(['cashier.secret' => 'sk_test_fake']);

    $product = new ConnectedProduct([
        'stripe_product_id' => 'prod_unit_test',
        'stripe_account_id' => 'acct_unit_test',
    ]);
    $product->id = 42;

    $mockProducts = Mockery::mock();
    $mockProducts->shouldReceive('update')
        ->once()
        ->with(
            'prod_unit_test',
            ['default_price' => 'price_new'],
            ['stripe_account' => 'acct_unit_test'],
        )
        ->andReturn((object) ['id' => 'prod_unit_test']);

    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->products = $mockProducts;

    $action = new class($mockStripe) extends SyncProductPrice
    {
        public function __construct(
            private readonly StripeClient $stripeClient,
        ) {}

        protected function makeStripeClient(string $secret): StripeClient
        {
            return $this->stripeClient;
        }
    };

    $method = new ReflectionMethod(SyncProductPrice::class, 'updateStripeProductDefaultPrice');
    $method->setAccessible(true);
    $method->invoke($action, $product, 'price_new');
});
