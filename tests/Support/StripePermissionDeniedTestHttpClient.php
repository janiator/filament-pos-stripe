<?php

namespace Tests\Support;

use Stripe\Exception\PermissionException;
use Stripe\HttpClient\ClientInterface;

/**
 * Returns a Stripe-style 403 so the PHP SDK raises {@see PermissionException}.
 */
final class StripePermissionDeniedTestHttpClient implements ClientInterface
{
    public function __construct(
        private readonly string $message = 'This API key does not have permission to perform this action on account acct_example. This may be due to duplicate account access.'
    ) {}

    /**
     * @param  'delete'|'get'|'post'  $method
     * @param  array<string, mixed>  $params
     * @param  'v1'|'v2'  $apiMode
     * @return array{0: string, 1: int, 2: array<string, mixed>}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
    {
        $body = json_encode([
            'error' => [
                'type' => 'invalid_request_error',
                'message' => $this->message,
            ],
        ], JSON_THROW_ON_ERROR);

        return [$body, 403, []];
    }
}
