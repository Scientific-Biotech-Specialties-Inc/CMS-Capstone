<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Internal lookup of a contract's creator, used to enforce "Sales: view own
 * only" on the Risk Assessment endpoints (US-026's role matrix: Manager
 * decision / Sales view, own only).
 */
class ContractOwnershipService
{
    protected string $baseUrl;
    protected string $secret;

    public function __construct()
    {
        $this->baseUrl = env('CONTRACT_SERVICE_URL', 'http://contract-management:8000/api');
        $this->secret  = env('INTERNAL_SERVICE_SECRET', '');
    }

    public function resolveContract(string|int $contractId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Accept'            => 'application/json',
                'X-Internal-Secret' => $this->secret,
            ])->timeout(5)->get("{$this->baseUrl}/internal/contracts/{$contractId}/owner");

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('ContractOwnershipService resolveContract connection error', [
                'contract_id' => $contractId,
                'message'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getCreatedBy(string|int $contractId): ?int
    {
        $info = $this->resolveContract($contractId);
        return $info['created_by'] ?? null;
    }

    public function resolveBatch(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'Accept'            => 'application/json',
                'X-Internal-Secret' => $this->secret,
            ])->timeout(5)->post("{$this->baseUrl}/internal/contracts/resolve-batch", [
                'ids' => array_values($ids),
            ]);

            return $response->successful() ? ($response->json('data') ?? []) : [];
        } catch (\Exception $e) {
            Log::error('ContractOwnershipService resolveBatch connection error', [
                'ids'     => $ids,
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
