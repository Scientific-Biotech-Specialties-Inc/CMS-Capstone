<?php

declare(strict_types=1);

namespace App\Services;

use Meilisearch\Client;

final class MeilisearchService
{
    private Client $client;

    public function __construct()
    {
        $host = (string) config('services.meilisearch.host', 'http://meilisearch:7700');
        $key = (string) config('services.meilisearch.key', 'changeme');
        $this->client = new Client($host, $key);
    }

    public function upsertContract(array $contract): void
    {
        $this->createIndexIfMissing();
        
        $document = array_merge(['id' => (int) $contract['contract_db_id']], $contract);
        $this->client->index('contracts')->addDocuments([$document]);
    }

    public function deleteContract(int $contractId): void
    {
        $this->createIndexIfMissing();
        $this->client->index('contracts')->deleteDocument((string) $contractId);
    }

    private function createIndexIfMissing(): void
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
