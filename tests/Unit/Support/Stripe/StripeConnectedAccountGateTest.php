<?php

use App\Exceptions\StripeConnectedAccountInaccessible;
use App\Support\Stripe\StripeConnectedAccountGate;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Stripe\StripeClient;
use Tests\Support\StripePermissionDeniedTestHttpClient;

afterEach(function (): void {
    ApiRequestor::setHttpClient(CurlClient::instance());
});

it('throws when the connected account id is empty', function (): void {
    expect(fn () => StripeConnectedAccountGate::assertPlatformMayUseConnectedAccount(
        new StripeClient('sk_test_empty_acct'),
        ''
    ))->toThrow(StripeConnectedAccountInaccessible::class);
});

it('wraps stripe permission errors for connected account retrieve', function (): void {
    ApiRequestor::setHttpClient(new StripePermissionDeniedTestHttpClient);

    expect(fn () => StripeConnectedAccountGate::assertPlatformMayUseConnectedAccount(
        new StripeClient('sk_test_gate'),
        'acct_gate_example'
    ))->toThrow(StripeConnectedAccountInaccessible::class);
});
