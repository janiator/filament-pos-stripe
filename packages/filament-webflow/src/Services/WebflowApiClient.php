<?php

namespace Positiv\FilamentWebflow\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebflowApiClient
{
    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl
    ) {}

    public static function forToken(string $token): self
    {
        $baseUrl = rtrim(config('filament-webflow.api_base_url', 'https://api.webflow.com/v2/'), '/').'/';

        return new self($token, $baseUrl);
    }

    /**
     * List all sites accessible with this token.
     *
     * @return array<string, mixed>
     */
    public function listSites(): array
    {
        return $this->request('GET', 'sites');
    }

    /**
     * Get a single site by ID.
     *
     * @return array<string, mixed>
     */
    public function getSite(string $siteId): array
    {
        return $this->request('GET', "sites/{$siteId}");
    }

    /**
     * List collections for a site.
     *
     * @return array<string, mixed>
     */
    public function listCollections(string $siteId): array
    {
        return $this->request('GET', "sites/{$siteId}/collections");
    }

    /**
     * Get a single collection by ID (includes field schema).
     *
     * @return array<string, mixed>
     */
    public function getCollection(string $collectionId): array
    {
        return $this->request('GET', "collections/{$collectionId}");
    }

    /**
     * List items in a collection with optional pagination.
     *
     * @return array<string, mixed>
     */
    public function listItems(string $collectionId, int $offset = 0, int $limit = 100): array
    {
        $query = http_build_query(array_filter([
            'offset' => $offset,
            'limit' => $limit,
        ]));
        $path = "collections/{$collectionId}/items".($query ? "?{$query}" : '');

        return $this->request('GET', $path);
    }

    /**
     * Get a single item from a collection.
     *
     * @return array<string, mixed>
     */
    public function getItem(string $collectionId, string $itemId): array
    {
        return $this->request('GET', "collections/{$collectionId}/items/{$itemId}");
    }

    /**
     * Check if an item has been published (lastPublished is not null).
     */
    public function isItemPublished(string $collectionId, string $itemId): bool
    {
        try {
            $item = $this->getItem($collectionId, $itemId);

            return isset($item['lastPublished']) && $item['lastPublished'] !== null;
        } catch (\Throwable $e) {
            Log::warning('Failed to check if Webflow item is published', [
                'collection_id' => $collectionId,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create an item in a collection (staged).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createItem(string $collectionId, array $payload): array
    {
        return $this->request('POST', "collections/{$collectionId}/items", $payload);
    }

    /**
     * Update an item in a collection (staged).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateItem(string $collectionId, string $itemId, array $payload): array
    {
        return $this->request('PATCH', "collections/{$collectionId}/items/{$itemId}", $payload);
    }

    /**
     * Create an item directly in live collection.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createItemLive(string $collectionId, array $payload): array
    {
        return $this->request('POST', "collections/{$collectionId}/items/live", $payload);
    }

    /**
     * Update an item directly in live collection.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateItemLive(string $collectionId, string $itemId, array $payload): array
    {
        return $this->request('PATCH', "collections/{$collectionId}/items/{$itemId}/live", $payload);
    }

    /**
     * Publish one or more staged items to live.
     *
     * @param  array<string>  $itemIds
     * @return array<string, mixed>
     */
    public function publishItems(string $collectionId, array $itemIds): array
    {
        return $this->request('POST', "collections/{$collectionId}/items/publish", [
            'itemIds' => $itemIds,
        ]);
    }

    /**
     * Delete (unpublish) a live item.
     *
     * @return array<string, mixed>
     */
    public function deleteItemLive(string $collectionId, string $itemId): array
    {
        return $this->request('DELETE', "collections/{$collectionId}/items/{$itemId}/live");
    }

    /**
     * Perform an HTTP request to the Webflow Data API.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $url = $this->baseUrl.ltrim($path, '/');

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->{$method}($url, $payload);

        if (! $response->successful()) {
            Log::error('Webflow API request failed', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $response->throw();
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }
}
