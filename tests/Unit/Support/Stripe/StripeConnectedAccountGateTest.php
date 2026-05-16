<?php

declare(strict_types=1);

use App\Exceptions\StripeConnectedAccountInaccessible;
use App\Support\Stripe\StripeConnectedAccountGate;
use PHPUnit\Framework\Assert;
use Stripe\Exception\PermissionException;
use Stripe\StripeClient;

it('raises StripeConnectedAccountInaccessible when the platform cannot retrieve the connected account', function (): void {
    $accountApi = Mockery::mock();
    $accountApi->shouldReceive('retrieve')
        ->once()
        ->with('acct_inaccessible_example')
        ->andThrow(PermissionException::factory(
            'The provided key \'sk_live_***\' does not have access to connected account acct_inaccessible_example (or such connected account does not exist), or application is not authenticated to retrieve this account.'
        ));

    $stripe = Mockery::mock(StripeClient::class);
    $stripe->accounts = $accountApi;

    try {
        StripeConnectedAccountGate::assertPlatformMayAccessConnectedAccount(
            $stripe,
            'acct_inaccessible_example',
        );

        Assert::fail('expected StripeConnectedAccountInaccessible');
    } catch (StripeConnectedAccountInaccessible $e) {
        expect($e->getMessage())->toBe(StripeConnectedAccountInaccessible::USER_MESSAGE);
        expect($e->validationErrors())->toBe([
            'stripe_account' => [StripeConnectedAccountInaccessible::USER_MESSAGE],
        ]);
    }
});

it('passes when the connected account is retrievable', function (): void {
    $accountApi = Mockery::mock();
    $accountApi->shouldReceive('retrieve')
        ->once()
        ->with('acct_connected_ok')
        ->andReturn(['id' => 'acct_connected_ok']);

    $stripe = Mockery::mock(StripeClient::class);
    $stripe->accounts = $accountApi;

    StripeConnectedAccountGate::assertPlatformMayAccessConnectedAccount(
        $stripe,
        'acct_connected_ok',
    );

    expect(true)->toBeTrue();
});

it('rethrows Stripe PermissionExceptions that are unrelated to disconnected connected-account access', function (): void {
    $stripeException = PermissionException::factory(
        'This API key lacks the stripe.pos.write permission.'
    );

    expect(fn (): mixed => StripeConnectedAccountGate::raiseIfDisconnectedConnectedAccountStripeCall($stripeException))
        ->toThrow(PermissionException::class);
});
