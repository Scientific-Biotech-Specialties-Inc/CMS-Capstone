<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin internal client to ai-service for reading a contract's latest risk
 * assessment level. Feature 1 (the RAG risk-assessment pipeline) populates
 * ai-service's `risk_assessment_results` table; this client only reads the
 * result, it never runs the assessment itself.
 */
class AiRiskService
{
    protected string $baseUrl;
    protected string $secret;

    public function __construct()
    {
        $this->baseUrl = env('AI_SERVICE_URL', 'http://ai-service:8000/api');
        $this->secret  = env('INTERNAL_SERVICE_SECRET', '');
    }

    /**
     * Get the latest risk level ('low'|'medium'|'high'|'critical') for a
     * contract, or null if no completed assessment exists yet.
     */
    public function getLatestRiskLevel(int $contractId): ?string
    {
        try {
            $response = Http::withHeaders([
                'Accept'            => 'application/json',
                'X-Internal-Secret' => $this->secret,
            ])->timeout(5)->get("{$this->baseUrl}/internal/contracts/{$contractId}/risk-level");

            if ($response->successful()) {
                return $response->json('risk_level');
            }

            if ($response->status() !== 404) {
                Log::warning('AiRiskService: unexpected response fetching risk level', [
                    'contract_id' => $contractId,
                    'status'      => $response->status(),
                ]);
            }

            return null;
        } catch (\Exception $e) {
            // Fail open: if ai-service is unreachable, do not block the approval
            // workflow entirely — treat as "no assessment available" rather than
            // erroring out the whole contract-approval flow.
            Log::error('AiRiskService connection error', [
                'contract_id' => $contractId,
                'message'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function isHighRisk(?string $riskLevel): bool
    {
        return in_array($riskLevel, ['high', 'critical'], true);
    }
}
