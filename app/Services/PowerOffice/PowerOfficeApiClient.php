<?php

namespace App\Services\PowerOffice;

use App\Enums\PowerOfficeEnvironment;
use App\Models\PowerOfficeIntegration;
use App\Support\PowerOffice\PowerOfficePostingSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PowerOfficeApiClient
{
    public function __construct(
        protected PowerOfficeOAuthTokenService $oauthTokens,
    ) {}

    public function baseUrl(PowerOfficeIntegration $integration): string
    {
        $key = $integration->environment === PowerOfficeEnvironment::Prod ? 'prod' : 'dev';

        return $this->validatedBaseUrlForEnvironmentKey($key);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function postLedgerEntry(PowerOfficeIntegration $integration, array $body): Response
    {
        $base = $this->validatedBaseUrlForEnvironmentKey(
            $integration->environment === PowerOfficeEnvironment::Prod ? 'prod' : 'dev',
        );
        $path = PowerOfficePostingSettings::ledgerPostPath($integration);
        if ($path === '') {
            throw new \RuntimeException(
                'PowerOffice ledger path is empty. Configure voucher posting mode in PowerOffice settings or POWEROFFICE_LEDGER_POST_PATH.'
            );
        }
        $url = $base.'/'.ltrim($path, '/');
        if (! $this->isAbsoluteHttpUrl($url)) {
            throw new \RuntimeException('PowerOffice ledger URL is not a valid absolute URL: '.$url);
        }

        return Http::withHeaders($this->headers($integration))
            ->acceptJson()
            ->asJson()
            ->timeout(60)
            ->post($url, $body);
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $query
     */
    public function get(PowerOfficeIntegration $integration, string $path, array $query = []): Response
    {
        $base = $this->validatedBaseUrlForEnvironmentKey(
            $integration->environment === PowerOfficeEnvironment::Prod ? 'prod' : 'dev',
        );
        $trimPath = ltrim($path, '/');
        $url = $base.'/'.$trimPath;
        if (! $this->isAbsoluteHttpUrl($url)) {
            throw new \RuntimeException('PowerOffice GET URL is not a valid absolute URL: '.$url);
        }

        return Http::withHeaders($this->headers($integration))
            ->acceptJson()
            ->timeout(60)
            ->get($url, $query);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function post(PowerOfficeIntegration $integration, string $path, array $body): Response
    {
        $base = $this->validatedBaseUrlForEnvironmentKey(
            $integration->environment === PowerOfficeEnvironment::Prod ? 'prod' : 'dev',
        );
        $url = $base.'/'.ltrim($path, '/');
        if (! $this->isAbsoluteHttpUrl($url)) {
            throw new \RuntimeException('PowerOffice POST URL is not a valid absolute URL: '.$url);
        }

        return Http::withHeaders($this->headers($integration))
            ->acceptJson()
            ->asJson()
            ->timeout(60)
            ->post($url, $body);
    }

    /**
     * Reverse a voucher previously posted by this integration (POST /Vouchers/Reverse/{id}).
     * Go creates a reversal voucher nullifying the original transactions and frees the
     * ExternalImportReference for reuse on a corrected voucher.
     */
    public function postVoucherReversal(PowerOfficeIntegration $integration, string $voucherGuid): Response
    {
        $base = $this->validatedBaseUrlForEnvironmentKey(
            $integration->environment === PowerOfficeEnvironment::Prod ? 'prod' : 'dev',
        );
        $url = $base.'/Vouchers/Reverse/'.rawurlencode($voucherGuid);
        if (! $this->isAbsoluteHttpUrl($url)) {
            throw new \RuntimeException('PowerOffice voucher reversal URL is not valid: '.$url);
        }

        return Http::withHeaders($this->headers($integration))
            ->acceptJson()
            ->asJson()
            ->timeout(60)
            ->post($url);
    }

    /**
     * Attach or replace PDF documentation on a posted voucher (API-imported vouchers only).
     *
     * @see https://developer.poweroffice.net — Voucher documentation
     */
    public function putVoucherDocumentation(
        PowerOfficeIntegration $integration,
        string $voucherGuid,
        string $pdfBinary,
        string $filename = 'z-report.pdf',
    ): Response {
        $base = $this->validatedBaseUrlForEnvironmentKey(
            $integration->environment === PowerOfficeEnvironment::Prod ? 'prod' : 'dev',
        );
        $path = trim((string) config('poweroffice.voucher_documentation.put_path'));
        if ($path === '') {
            throw new \RuntimeException('PowerOffice voucher documentation path is empty.');
        }
        $url = $base.'/'.ltrim($path, '/').'?id='.rawurlencode($voucherGuid);
        if (! $this->isAbsoluteHttpUrl($url)) {
            throw new \RuntimeException('PowerOffice voucher documentation URL is not valid: '.$url);
        }

        $headers = $this->multipartAuthHeaders($integration);

        return Http::withHeaders($headers)
            ->timeout(120)
            ->attach('file', $pdfBinary, $filename, ['Content-Type' => 'application/pdf'])
            ->put($url);
    }

    /**
     * @return array<string, string>
     */
    protected function multipartAuthHeaders(PowerOfficeIntegration $integration): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        $headers['Authorization'] = 'Bearer '.$this->oauthTokens->getValidAccessToken($integration);

        $subscriptionKey = config('poweroffice.subscription_key');
        if (filled($subscriptionKey)) {
            $headers['Ocp-Apim-Subscription-Key'] = (string) $subscriptionKey;
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function postOnboardingInit(array $body, PowerOfficeEnvironment $environment): Response
    {
        $key = $environment === PowerOfficeEnvironment::Prod ? 'prod' : 'dev';
        $base = $this->validatedBaseUrlForEnvironmentKey($key);
        $path = trim((string) config('poweroffice.onboarding.init_path'));
        if ($path === '') {
            throw new \RuntimeException('PowerOffice onboarding init_path is empty.');
        }
        $url = $base.'/'.ltrim($path, '/');
        if (! $this->isAbsoluteHttpUrl($url)) {
            throw new \RuntimeException('PowerOffice onboarding init URL is not valid: '.$url);
        }

        return $this->onboardingHttp()
            ->post($url, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function postOnboardingComplete(array $body, PowerOfficeEnvironment $environment): Response
    {
        $key = $environment === PowerOfficeEnvironment::Prod ? 'prod' : 'dev';
        $base = $this->validatedBaseUrlForEnvironmentKey($key);
        $path = trim((string) config('poweroffice.onboarding.complete_path'));
        if ($path === '') {
            throw new \RuntimeException('PowerOffice onboarding complete_path is empty.');
        }
        $url = $base.'/'.ltrim($path, '/');
        if (! $this->isAbsoluteHttpUrl($url)) {
            throw new \RuntimeException('PowerOffice onboarding finalize URL is not valid: '.$url);
        }

        return $this->onboardingHttp()
            ->post($url, $body);
    }

    /**
     * Onboarding v2 calls use an API Management subscription key (see PowerOffice Onboarding OpenAPI).
     */
    protected function onboardingHttp(): PendingRequest
    {
        $request = Http::acceptJson()
            ->asJson()
            ->timeout(60);

        $subscriptionKey = config('poweroffice.subscription_key');
        if (filled($subscriptionKey)) {
            $request = $request->withHeaders([
                'Ocp-Apim-Subscription-Key' => (string) $subscriptionKey,
            ]);
        }

        return $request;
    }

    /**
     * @return array<string, string>
     */
    protected function headers(PowerOfficeIntegration $integration): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $headers['Authorization'] = 'Bearer '.$this->oauthTokens->getValidAccessToken($integration);

        $subscriptionKey = config('poweroffice.subscription_key');
        if (filled($subscriptionKey)) {
            $headers['Ocp-Apim-Subscription-Key'] = (string) $subscriptionKey;
        }

        return $headers;
    }

    public function logFailedResponse(string $context, Response $response): void
    {
        Log::warning('PowerOffice API error', [
            'context' => $context,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }

    /**
     * Short human-readable suffix for sync error messages (logged body is full).
     */
    public function summarizeErrorBody(Response $response): string
    {
        $body = trim($response->body());
        if ($body === '') {
            return '';
        }

        $json = $response->json();
        if (is_array($json)) {
            $parts = [];
            foreach (['Message', 'message', 'title', 'error', 'detail', 'Detail'] as $key) {
                $msg = $json[$key] ?? null;
                if (is_string($msg) && $msg !== '' && ! in_array($msg, $parts, true)) {
                    $parts[] = $msg;
                }
                if (is_array($msg)) {
                    foreach ($msg as $item) {
                        if (is_string($item) && $item !== '' && ! in_array($item, $parts, true)) {
                            $parts[] = $item;
                        }
                    }
                }
            }

            foreach (['errors', 'Errors', 'validationErrors', 'ValidationErrors'] as $key) {
                $errors = $json[$key] ?? null;
                if (! is_array($errors)) {
                    continue;
                }
                foreach ($errors as $field => $messages) {
                    $prefix = is_string($field) ? $field.': ' : '';
                    foreach ((array) $messages as $message) {
                        if (is_string($message) && $message !== '') {
                            $parts[] = $prefix.$message;
                        }
                    }
                }
            }

            if ($parts !== []) {
                $message = implode(' | ', array_slice(array_values(array_unique($parts)), 0, 8));

                return ': '.$message.(count($parts) > 8 ? ' …' : '');
            }
        }

        $snippet = mb_substr($body, 0, 400);

        return ': '.$snippet.(mb_strlen($body) > 400 ? '…' : '');
    }

    protected function validatedBaseUrlForEnvironmentKey(string $environmentKey): string
    {
        $raw = trim((string) config("poweroffice.base_urls.{$environmentKey}"));
        $base = rtrim($raw, '/');
        if ($base === '') {
            throw new \RuntimeException(
                'PowerOffice base URL is empty. Set POWEROFFICE_DEMO_BASE_URL (dev) or POWEROFFICE_PROD_BASE_URL (prod) to a full URL such as https://goapi.poweroffice.net/demo/v2.'
            );
        }

        if (! $this->isAbsoluteHttpUrl($base)) {
            throw new \RuntimeException(
                'PowerOffice base URL must be an absolute http(s) URL; got: '.$base
            );
        }

        return $base;
    }

    protected function isAbsoluteHttpUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);

        return in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && filled($parts['host'] ?? null);
    }
}
