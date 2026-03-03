<?php

namespace Positiv\FilamentWebflow\Actions;

use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Models\WebflowSite;
use Positiv\FilamentWebflow\Services\WebflowApiClient;

class DiscoverCollections
{
    public function __invoke(WebflowSite $site): array
    {
        $token = $site->api_token;
        if (empty($token)) {
            throw new \InvalidArgumentException('Webflow site has no API token set.');
        }

        $client = WebflowApiClient::forToken($token);
        $response = $client->listCollections($site->webflow_site_id);

        // API v2 returns { "collections": [ { "id", "displayName", "slug", ... } ] }
        $collections = $response['collections'] ?? $response['items'] ?? [];
        if (! is_array($collections)) {
            return ['discovered' => 0, 'message' => 'Unexpected API response structure.'];
        }

        $discovered = 0;
        foreach ($collections as $coll) {
            $webflowCollectionId = $coll['id'] ?? null;
            if (! $webflowCollectionId) {
                continue;
            }

            $fullCollection = $client->getCollection($webflowCollectionId);
            $schema = $this->extractSchema($fullCollection);

            WebflowCollection::updateOrCreate(
                [
                    'webflow_site_id' => $site->id,
                    'webflow_collection_id' => $webflowCollectionId,
                ],
                [
                    'name' => $fullCollection['displayName'] ?? $coll['displayName'] ?? $webflowCollectionId,
                    'slug' => $fullCollection['slug'] ?? $coll['slug'] ?? null,
                    'schema' => $schema,
                    'field_mapping' => null,
                ]
            );
            $discovered++;
        }

        return ['discovered' => $discovered];
    }

    /**
     * Extract field schema from Webflow collection response.
     *
     * @param  array<string, mixed>  $collection
     * @return array<int, array<string, mixed>>
     */
    private function extractSchema(array $collection): array
    {
        $fields = $collection['fields'] ?? [];
        if (! is_array($fields)) {
            return [];
        }

        $schema = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $schema[] = [
                'id' => $field['id'] ?? null,
                'slug' => $field['slug'] ?? null,
                'displayName' => $field['displayName'] ?? null,
                'type' => $field['type'] ?? 'PlainText',
                'required' => $field['required'] ?? false,
            ];
        }

        return $schema;
    }
}
