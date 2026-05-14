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
        if (! is_array($json)) {
            $snippet = mb_substr($body, 0, 400);

            return ': '.$snippet.(mb_strlen($body) > 400 ? '…' : '');
        }

        $parts = [];
        foreach (['message', 'error', 'developerMessage', 'readableError'] as $key) {
            $v = $json[$key] ?? null;
            if (is_string($v) && $v !== '') {
                $parts[] = $v;
            }
        }

        self::collectNestedValidationMessages($parts, $json);

        $parts = array_values(array_unique(array_filter(array_map('trim', $parts))));
        if ($parts === []) {
            $encoded = json_encode($json, JSON_UNESCAPED_UNICODE);
            $snippet = is_string($encoded) ? mb_substr($encoded, 0, 400) : '';

            return $snippet !== '' ? ': '.$snippet.(mb_strlen($encoded) > 400 ? '…' : '') : '';
        }

        $merged = implode(' — ', $parts);
        if (mb_strlen($merged) > 1200) {
            $merged = mb_substr($merged, 0, 1200).'…';
        }

        return ': '.$merged;
    }

    /**
     * Human-readable summary plus full Tripletex response body for sync run storage / support.
     * Uses {@see summarizeErrorBody()} for nested validation lines, then appends the raw JSON (pretty-printed).
     */
    public function describeFailedVoucherResponse(Response $response, int $maxBodyChars = 24_000): string
    {
        $status = $response->status();
        $summary = ltrim($this->summarizeErrorBody($response), ': ');

        $body = trim($response->body());
        $fullBody = '';
        if ($body !== '') {
            $decoded = $response->json();
            $fullBody = is_array($decoded)
                ? (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                : $body;
        }

        if ($fullBody !== '' && mb_strlen($fullBody) > $maxBodyChars) {
            $fullBody = mb_substr($fullBody, 0, $maxBodyChars)."\n… (response body truncated)";
        }

        $parts = ["Tripletex HTTP {$status}"];
        if ($summary !== '') {
            $parts[] = $summary;
        }
        if ($fullBody !== '') {
            $parts[] = "Tripletex response body:\n".$fullBody;
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param  list<string>  $parts
     * @param  array<string, mixed>  $node
     */
    private static function collectNestedValidationMessages(array &$parts, array $node, int $depth = 0): void
    {
        if ($depth > 8) {
            return;
        }

        $lists = [
            $node['validationMessages'] ?? null,
            $node['messages'] ?? null,
            $node['errors'] ?? null,
            $node['violations'] ?? null,
        ];
        foreach ($lists as $list) {
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $item) {
                if (is_string($item) && $item !== '') {
                    $parts[] = $item;

                    continue;
                }
                if (! is_array($item)) {
                    continue;
                }
                $m = $item['message'] ?? $item['msg'] ?? $item['text'] ?? null;
                $f = $item['field'] ?? $item['property'] ?? $item['path'] ?? null;
                if (is_string($m) && $m !== '') {
                    $parts[] = is_string($f) && $f !== '' ? "{$f}: {$m}" : $m;
                }
            }
        }

        foreach (['value', 'data', 'result', 'error', 'details'] as $key) {
            $child = $node[$key] ?? null;
            if (is_array($child)) {
                self::collectNestedValidationMessages($parts, $child, $depth + 1);
            }
        }
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
