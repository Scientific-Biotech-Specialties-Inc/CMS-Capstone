<?php

declare(strict_types=1);

namespace App\Services;

use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;

final class MeilisearchService
{
    private Client $client;

    public function __construct()
    {
        $host = (string) config('services.meilisearch.host', 'http://meilisearch:7700');
        $key = (string) config('services.meilisearch.key', 'changeme');
        $this->client = new Client($host, $key);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getIndex(): Indexes
    {
        $this->createIndexIfMissing();
        return $this->client->index('contracts');
    }

    public function search(string $query, array $options = []): array
    {
        try {
            return $this->getIndex()->search($query, $options)->toArray();
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            if ($e->errorCode === 'index_not_found') {
                return ['hits' => [], 'estimatedTotalHits' => 0];
            }
            throw $e;
        }
    }

    public function indexDocuments(array $documents): void
    {
        $this->getIndex()->addDocuments($documents);
    }

    public function indexIsEmpty(): bool
    {
        try {
            $stats = $this->client->index('contracts')->stats();
            return ($stats['numberOfDocuments'] ?? 0) === 0;
        } catch (\Exception) {
            return true;
        }
    }

    public function createIndexIfMissing(): void
    {
        try {
            $this->client->getIndex('contracts');
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            if ($e->errorCode === 'index_not_found') {
                $this->client->createIndex('contracts', ['primaryKey' => 'id']);
            }
        } catch (\Exception) {
            // Silently absorb
        }
    }
}
