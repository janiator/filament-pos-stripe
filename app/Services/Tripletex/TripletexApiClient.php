<?php

namespace App\Services\Tripletex;

use App\Enums\TripletexEnvironment;
use App\Models\TripletexIntegration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TripletexApiClient
{
    public function baseUrl(TripletexEnvironment $environment): string
    {
        $key = $environment === TripletexEnvironment::Prod ? 'prod' : 'test';
        $raw = trim((string) config("tripletex.base_urls.{$key}"));
        $base = rtrim($raw, '/');
        if ($base === '' || ! $this->isAbsoluteHttpUrl($base)) {
            throw new \RuntimeException('Tripletex base URL is empty or invalid for environment '.$key);
        }

        return $base;
    }

    /**
     * Create a Tripletex session token and return Basic-auth credentials (`base64("0:" . token)`).
     */
    public function createSessionToken(TripletexIntegration $integration, ?\Carbon\CarbonInterface $expirationDate = null): string
    {
        $consumer = $integration->consumer_token;
        $employee = $integration->employee_token;
        if (! filled($consumer) || ! filled($employee)) {
            throw new \RuntimeException('Tripletex consumer or employee token is missing.');
        }

        $base = $this->baseUrl($integration->environment);
        $path = ltrim((string) config('tripletex.session.create_path'), '/');
        $url = $base.'/'.$path;

        $expiration = ($expirationDate ?? now()->addDays(2))->format('Y-m-d');

        $response = Http::acceptJson()
            ->timeout((int) config('tripletex.timeout_seconds', 60))
            ->withQueryParameters([
                'consumerToken' => $consumer,
                'employeeToken' => $employee,
                'expirationDate' => $expiration,
            ])
            ->put($url);

        if (! $response->successful()) {
            $this->logFailedResponse('tripletex_session_create', $response);
            throw new \RuntimeException('Tripletex session token request failed (HTTP '.$response->status().').');
        }

        $json = $response->json();
        $token = data_get($json, 'value.token');
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Tripletex session token response missing value.token.');
        }

        return base64_encode('0:'.$token);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAccountByNumber(string $sessionToken, TripletexEnvironment $environment, int|string $accountNumber): ?array
    {
        $number = is_int($accountNumber) ? $accountNumber : (int) trim((string) $accountNumber);
        if ($number <= 0) {
            return null;
        }

        $base = $this->baseUrl($environment);
        $url = $base.'/ledger/account';
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Basic '.$sessionToken,
        ])
            ->timeout((int) config('tripletex.timeout_seconds', 60))
            ->get($url, [
                'number' => $number,
                'from' => 0,
                'count' => 1000,
            ]);

        if (! $response->successful()) {
            $this->logFailedResponse('tripletex_ledger_account', $response);

            return null;
        }

        $values = $response->json('values');
        if (! is_array($values) || $values === []) {
            return null;
        }

        $first = $values[0];

        return is_array($first) ? $first : null;
    }

    /**
     * @param  array<string, mixed>  $voucherBody
     */
    public function postVoucher(string $sessionToken, TripletexEnvironment $environment, array $voucherBody): Response
    {
        $base = $this->baseUrl($environment);
        $path = ltrim((string) config('tripletex.voucher.post_path'), '/');
        $sendToLedger = filter_var(config('tripletex.voucher.send_to_ledger'), FILTER_VALIDATE_BOOL);
        $url = $base.'/'.$path.'?'.http_build_query(['sendToLedger' => $sendToLedger ? 'true' : 'false']);

        return Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic '.$sessionToken,
        ])
            ->timeout((int) config('tripletex.timeout_seconds', 60))
            ->post($url, $voucherBody);
    }

    public function logFailedResponse(string $context, Response $response): void
    {
        Log::warning('Tripletex API error', [
            'context' => $context,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }

    public function summarizeErrorBody(Response $response): string
    {
        $body = trim($response->body());
        if ($body === '') {
            return '';
        }

        $json = $response->json();
        if (is_array($json)) {
            $msg = $json['message'] ?? $json['error'] ?? $json['developerMessage'] ?? null;
            if (is_string($msg) && $msg !== '') {
                return ': '.$msg;
            }
        }

        $snippet = mb_substr($body, 0, 400);

        return ': '.$snippet.(mb_strlen($body) > 400 ? '…' : '');
    }

    protected function isAbsoluteHttpUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);

        return isset($parts['scheme'], $parts['host'])
            && in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true);
    }
}
