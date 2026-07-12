<?php

declare(strict_types=1);

use App\Actions\Webhooks\HandleChargeWebhook;
use App\Actions\Webhooks\HandleRefundWebhook;
use Stripe\Charge;
use Stripe\Refund;

afterEach(function (): void {
    Mockery::close();
});

it('retrieves charge and forwards to HandleChargeWebhook', function (): void {
    $stubCharge = Charge::constructFrom([
        'id' => 'ch_unit_refund123',
        'payment_intent' => null,
        'customer' => null,
        'amount' => 5000,
        'amount_refunded' => 2500,
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
        'outcome' => [],
        'on_behalf_of' => 'acct_unit_refund123',
        'destination' => null,
        'application_fee_amount' => null,
        'payment_method_details' => ['type' => 'card'],
    ]);

    $chargeHandler = Mockery::mock(HandleChargeWebhook::class);
    $chargeHandler->shouldReceive('handle')
        ->once()
        ->with(
            Mockery::on(fn ($charge): bool => $charge instanceof Charge && $charge->id === 'ch_unit_refund123'),
            'charge.refund.updated',
            'acct_unit_refund123'
        );

    $sut = new class($chargeHandler, $stubCharge) extends HandleRefundWebhook
    {
        public function __construct(
            HandleChargeWebhook $handleChargeWebhook,
            private readonly Charge $stubCharge,
        ) {
            parent::__construct($handleChargeWebhook);
        }

        protected function retrieveChargeFromStripe(string $chargeId, ?string $accountId): ?Charge
        {
            expect($chargeId)->toBe('ch_unit_refund123')
                ->and($accountId)->toBe('acct_unit_refund123');

            return $this->stubCharge;
        }
    };

    $refund = Refund::constructFrom([
        'id' => 're_unit_refund123',
        'object' => 'refund',
        'charge' => 'ch_unit_refund123',
        'amount' => 2500,
        'currency' => 'nok',
        'status' => 'succeeded',
        'created' => time(),
        'metadata' => [],
    ]);

    $sut->handle($refund, 'charge.refund.updated', 'acct_unit_refund123');
});
