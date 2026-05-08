<?php

namespace App\Services\PowerOffice;

use App\Enums\PowerOfficeEnvironment;
use App\Enums\PowerOfficeIntegrationStatus;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PowerOfficeOnboardingService
{
    public function __construct(
        protected PowerOfficeApiClient $apiClient,
    ) {}

    /**
     * Start onboarding: returns URL to open in browser for PowerOffice Go.
     */
    public function initiate(Store $store, PowerOfficeIntegration $integration): string
    {
        $state = Str::random(48);
        $integration->onboarding_state_token = $state;
        $integration->status = PowerOfficeIntegrationStatus::PendingOnboarding;
        $integration->save();

        Cache::put($this->cacheKey($state), $store->getKey(), now()->addHours(2));

        if (! filled(config('poweroffice.client_id'))) {
            throw new \RuntimeException(
                'PowerOffice is not configured on the server: set POWEROFFICE_CLIENT_ID (your Application Key UUID) and POWEROFFICE_SUBSCRIPTION_KEY in the environment. Documentation: https://developer.poweroffice.net/documentation'
            );
        }

        $redirectUri = $this->redirectUriWithState($state);

        $body = [
            'ApplicationKey' => (string) config('poweroffice.client_id'),
            'RedirectUri' => $redirectUri,
        ];

        $environment = $integration->environment ?? PowerOfficeEnvironment::Dev;

        $response = $this->apiClient->postOnboardingInit($body, $environment);

        if (! $response->successful()) {
            $this->apiClient->logFailedResponse('onboarding_init', $response);
            throw new \RuntimeException('PowerOffice onboarding init failed: HTTP '.$response->status());
        }

        $json = $response->json();
        $url = $json['TemporaryUrl'] ?? $json['temporaryUrl'] ?? $json['url'] ?? $json['onboardingUrl'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new \RuntimeException('PowerOffice onboarding init returned no URL.');
        }

        return $url;
    }

    /**
     * Exchange onboarding token for client credentials (server callback).
     *
     * @param  array<string, mixed>  $payload
     */
    public function completeFromCallback(array $payload): void
    {
        $state = $payload['state'] ?? null;
        if (! is_string($state) || $state === '') {
            throw new \InvalidArgumentException('Missing onboarding state.');
        }

        $storeId = Cache::pull($this->cacheKey($state));
        if (! is_int($storeId) && ! is_numeric($storeId)) {
            throw new \InvalidArgumentException('Invalid or expired onboarding state.');
        }

        $integration = PowerOfficeIntegration::query()
            ->where('store_id', (int) $storeId)
            ->where('onboarding_state_token', $state)
            ->firstOrFail();

        $token = $payload['token'] ?? $payload['onboardingToken'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new \InvalidArgumentException('Missing onboarding token.');
        }

        $completeBody = [
            'OnboardingToken' => $token,
        ];

        if (filled(config('poweroffice.client_id'))) {
            $response = $this->apiClient->postOnboardingComplete($completeBody, $integration->environment ?? PowerOfficeEnvironment::Dev);
            if (! $response->successful()) {
                $this->apiClient->logFailedResponse('onboarding_complete', $response);
                $integration->status = PowerOfficeIntegrationStatus::Error;
                $integration->last_error = 'Onboarding complete failed: HTTP '.$response->status();
                $integration->save();

                throw new \RuntimeException($integration->last_error);
            }

            $json = $response->json();
            $clientKey = $this->clientKeyFromFinalizeResponse(is_array($json) ? $json : []);
            if (is_string($clientKey) && $clientKey !== '') {
                $integration->client_key = $clientKey;
            }
        } else {
            $integration->client_key = 'demo-placeholder-'.Str::uuid()->toString();
        }

        $integration->status = PowerOfficeIntegrationStatus::Connected;
        $integration->last_onboarded_at = now();
        $integration->last_error = null;
        $integration->onboarding_state_token = null;
        $integration->save();
    }

    protected function cacheKey(string $state): string
    {
        return 'poweroffice_onboarding:'.$state;
    }

    protected function redirectUriWithState(string $state): string
    {
        $base = config('poweroffice.urls.redirect');
        if (! filled($base)) {
            $base = url('/integrations/poweroffice/onboarding/redirect');
        }

        $separator = str_contains((string) $base, '?') ? '&' : '?';

        return (string) $base.$separator.http_build_query(['state' => $state]);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function clientKeyFromFinalizeResponse(array $json): ?string
    {
        $clients = $json['OnboardedClientsInformation'] ?? $json['onboardedClientsInformation'] ?? null;
        if (is_array($clients) && $clients !== []) {
            $first = $clients[0];
            if (is_array($first)) {
                $key = $first['ClientKey'] ?? $first['client_key'] ?? null;
                if (is_string($key) && $key !== '') {
                    return $key;
                }
            }
        }

        $flat = $json['clientKey'] ?? $json['client_key'] ?? $json['apiKey'] ?? null;

        return is_string($flat) && $flat !== '' ? $flat : null;
    }
}
