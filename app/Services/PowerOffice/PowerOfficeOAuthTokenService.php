<?php

namespace App\Services\PowerOffice;

use App\Enums\PowerOfficeEnvironment;
use App\Models\PowerOfficeIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches and caches PowerOffice Go API v2 OAuth access tokens (client credentials grant).
 *
 * @see https://developer.poweroffice.net/documentation/authentication
 */
class PowerOfficeOAuthTokenService
{
    /**
     * Returns a non-expired access token for the integration, refreshing when needed.
     */
    public function getValidAccessToken(PowerOfficeIntegration $integration): string
    {
        if (! filled(config('poweroffice.client_id'))) {
            throw new \RuntimeException(
                'POWEROFFICE_CLIENT_ID (PowerOffice application key) is not configured.'
            );
        }

        if (! filled($integration->client_key)) {
            throw new \RuntimeException('PowerOffice client key is missing; complete onboarding for this store.');
        }

        $integration->refresh();

        if (filled($integration->access_token) && $integration->token_expires_at instanceof \Carbon\CarbonInterface) {
            if ($integration->token_expires_at->isAfter(now()->addSeconds(90))) {
                return $integration->access_token;
            }
        }

        return $this->fetchAndPersist($integration);
    }

    protected function fetchAndPersist(PowerOfficeIntegration $integration): string
    {
        $envKey = $integration->environment === PowerOfficeEnvironment::Prod ? 'prod' : 'dev';
        $tokenUrl = trim((string) config("poweroffice.oauth.token_url.{$envKey}"));
        if ($tokenUrl === '') {
            throw new \RuntimeException("PowerOffice OAuth token URL is empty for environment [{$envKey}].");
        }

        $subscriptionKey = config('poweroffice.subscription_key');
        if (! filled($subscriptionKey)) {
            throw new \RuntimeException('POWEROFFICE_SUBSCRIPTION_KEY is not configured.');
        }

        $applicationKey = (string) config('poweroffice.client_id');
        $clientKey = (string) $integration->client_key;
        $basic = base64_encode($applicationKey.':'.$clientKey);

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$basic,
            'Ocp-Apim-Subscription-Key' => (string) $subscriptionKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ])
            ->asForm()
            ->timeout(30)
            ->post($tokenUrl, [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            Log::warning('PowerOffice OAuth token request failed', [
                'store_id' => $integration->store_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException(
                'PowerOffice OAuth token request failed: HTTP '.$response->status()
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new \RuntimeException('PowerOffice OAuth token response was not JSON.');
        }

        $accessToken = $json['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new \RuntimeException('PowerOffice OAuth token response missing access_token.');
        }

        $expiresIn = (int) ($json['expires_in'] ?? 1200);
        $bufferSeconds = 120;
        $integration->access_token = $accessToken;
        $integration->token_expires_at = now()->addSeconds(max(60, $expiresIn - $bufferSeconds));
        $integration->save();

        return $accessToken;
    }
}
