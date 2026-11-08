<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Internal client to contract-management for fetching a contract's document
 * metadata and raw file bytes, needed by the RAG risk-assessment pipeline's
 * text-extraction step (Feature 1).
 */
class ContractDocumentClient
{
    protected string $baseUrl;
    protected string $secret;

    public function __construct()
    {
        $this->baseUrl = env('CONTRACT_SERVICE_URL', 'http://contract-management:8000/api');
        $this->secret  = env('INTERNAL_SERVICE_SECRET', '');
    }

    /**
     * @return array{document_id: string, file_name: string, file_type: string}[] 
     */
    public function listDocuments(string|int $contractId): array
    {
        try {
            $response = Http::withHeaders([
                'Accept'            => 'application/json',
                'X-Internal-Secret' => $this->secret,
            ])->get("{$this->baseUrl}/internal/contracts/{$contractId}/documents");

            if ($response->successful()) {
                return $response->json('data') ?? [];
            }

            Log::warning('ContractDocumentClient: listDocuments failed', [
                'contract_id' => $contractId,
                'status'      => $response->status(),
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('ContractDocumentClient: listDocuments connection error', [
                'contract_id' => $contractId,
                'message'     => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function fetchFileBytes(string $documentId): ?string
    {
        try {
            $response = Http::withHeaders([
                'Accept'            => 'application/octet-stream',
                'X-Internal-Secret' => $this->secret,
            ])->get("{$this->baseUrl}/internal/documents/{$documentId}/file");

            return $response->successful() ? $response->body() : null;
        } catch (\Exception $e) {
            Log::error('ContractDocumentClient: fetchFileBytes connection error', [
                'document_id' => $documentId,
                'message'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getDocumentMetadata(string $documentId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Accept'            => 'application/json',
                'X-Internal-Secret' => $this->secret,
            ])->get("{$this->baseUrl}/internal/documents/{$documentId}");

            return $response->successful() ? $response->json('data') : null;
        } catch (\Exception $e) {
            Log::error('ContractDocumentClient: getDocumentMetadata connection error', [
                'document_id' => $documentId,
                'message'     => $e->getMessage(),
            ]);
            return null;
        }
    }
}
