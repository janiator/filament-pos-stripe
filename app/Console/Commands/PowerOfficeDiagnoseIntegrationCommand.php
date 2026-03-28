<?php

namespace App\Console\Commands;

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\Store;
use App\Services\PowerOffice\PowerOfficeApiClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;

/**
 * Lists effective API privileges for a store's PowerOffice integration (why 403 happens).
 *
 * @see https://developer.poweroffice.net/endpoints/client-settings/client-integration-information
 * @see https://developer.poweroffice.net/documentation/authentication (Authorised access privileges)
 */
class PowerOfficeDiagnoseIntegrationCommand extends Command
{
    protected $signature = 'poweroffice:diagnose {store_slug : Store slug (e.g. jobberiet-as)}';

    protected $description = 'Show PowerOffice client integration info (privileges / subscriptions) for debugging 403 errors';

    public function handle(PowerOfficeApiClient $apiClient): int
    {
        $slug = (string) $this->argument('store_slug');
        $store = Store::query()->where('slug', $slug)->first();
        if (! $store) {
            $this->error("Store not found for slug: {$slug}");

            return self::FAILURE;
        }

        if (! Addon::storeHasActiveAddon($store->getKey(), AddonType::PowerOfficeGo)) {
            $this->error('PowerOffice Go add-on is not active for this store.');

            return self::FAILURE;
        }

        $integration = $store->powerOfficeIntegration;
        if (! $integration || ! $integration->isConnected()) {
            $this->error('PowerOffice integration is missing or not connected.');

            return self::FAILURE;
        }

        $base = $apiClient->baseUrl($integration);
        $postPath = trim((string) config('poweroffice.ledger.post_path'));
        $this->line('Base URL: '.$base);
        $this->line('Ledger POST path: '.$postPath);
        $this->newLine();

        $configuredPath = trim((string) config('poweroffice.diagnostics.client_integration_information_path'));
        $candidates = $configuredPath !== ''
            ? [$configuredPath]
            : [
                '/ClientIntegrationInformation',
                '/Clients/ClientIntegrationInformation',
                '/ClientSettings/ClientIntegrationInformation',
                '/Settings/ClientIntegrationInformation',
            ];

        $response = null;
        $usedPath = null;
        foreach ($candidates as $path) {
            $path = '/'.ltrim($path, '/');
            try {
                $response = $apiClient->get($integration, $path);
            } catch (\Throwable $e) {
                $this->error('Request failed: '.$e->getMessage());

                return self::FAILURE;
            }
            if ($response->successful()) {
                $usedPath = $path;
                break;
            }
            if ($response->status() !== 404) {
                $usedPath = $path;
                break;
            }
        }

        if ($response === null) {
            $this->error('No response from PowerOffice.');

            return self::FAILURE;
        }

        $this->line('Client integration info path tried: '.($usedPath ?? '(none)'));
        $this->line('HTTP status: '.$response->status());
        $this->dumpResponse($response);

        if (! $response->successful()) {
            $this->newLine();
            $this->warn('Could not load Client Integration Information. Try setting POWEROFFICE_CLIENT_INTEGRATION_INFO_PATH to the path from your PowerOffice Swagger (Client Settings → Client Integration Information).');

            return self::FAILURE;
        }

        $this->newLine();
        $this->comment('If manual journal POST returns 403: compare valid privileges above with the operation you use ('.$postPath.').');
        $this->comment('Onboarding only proves the client activated your app; posting needs those privileges on your application key (often arranged via go-api@poweroffice.no).');

        return self::SUCCESS;
    }

    protected function dumpResponse(Response $response): void
    {
        $json = $response->json();
        if (is_array($json)) {
            $this->line(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        $body = trim($response->body());
        if ($body !== '') {
            $this->line(mb_substr($body, 0, 4000).(mb_strlen($body) > 4000 ? '…' : ''));
        } else {
            $this->line('(empty body)');
        }
    }
}
