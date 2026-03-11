<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class MeranoConnectionService
{
    /**
     * Whether the current database supports Merano configuration fields.
     */
    public function supportsStoreConfiguration(): bool
    {
        return Schema::hasColumns('stores', [
            'merano_base_url',
            'merano_pos_api_token',
        ]);
    }

    /**
     * Test the configured Merano connection for a store by calling the events endpoint.
     *
     * @return array{ok: bool, message: string, status: int|null, events_count: int|null}
     */
    public function testConnection(Store $store): array
    {
        if (! $this->supportsStoreConfiguration()) {
            return [
                'ok' => false,
                'message' => 'Merano configuration columns are missing. Run the latest database migrations first.',
                'status' => null,
                'events_count' => null,
            ];
        }

        if (! filled($store->merano_base_url) || ! filled($store->merano_pos_api_token)) {
            return [
                'ok' => false,
                'message' => 'Merano base URL and POS API token must both be configured before testing.',
                'status' => null,
                'events_count' => null,
            ];
        }

        try {
            $response = Http::baseUrl(rtrim((string) $store->merano_base_url, '/'))
                ->acceptJson()
                ->withToken((string) $store->merano_pos_api_token)
                ->withHeaders([
                    'X-POS-API-Token' => (string) $store->merano_pos_api_token,
                ])
                ->timeout(15)
                ->get('/api/pos/v1/events');
        } catch (ConnectionException $exception) {
            return [
                'ok' => false,
                'message' => 'Could not reach Merano: '.$exception->getMessage(),
                'status' => null,
                'events_count' => null,
            ];
        }

        if (! $response->successful()) {
            $body = trim($response->body());
            $suffix = $body !== '' ? ' '.$body : '';

            return [
                'ok' => false,
                'message' => "Merano responded with HTTP {$response->status()}.{$suffix}",
                'status' => $response->status(),
                'events_count' => null,
            ];
        }

        $data = $response->json();
        $events = data_get($data, 'data');
        $eventsCount = is_array($events) ? count($events) : null;
        $message = $eventsCount !== null
            ? "Connection OK. Merano returned {$eventsCount} event(s)."
            : 'Connection OK. Merano responded successfully.';

        return [
            'ok' => true,
            'message' => $message,
            'status' => $response->status(),
            'events_count' => $eventsCount,
        ];
    }
}
