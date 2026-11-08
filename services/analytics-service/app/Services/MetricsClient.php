<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Feature 4: internal client pulling metrics snapshots from each source
 * service via X-Internal-Secret-authenticated HTTP calls — not
 * cross-database joins, preserving the existing schema-isolation design
 * (Analytics/AI/each service intentionally live in separate database
 * engines/schemas).
 */
class MetricsClient
{
    protected string $secret;
    protected string $contractServiceUrl;
    protected string $vendorServiceUrl;
    protected string $notificationServiceUrl;
    protected string $aiServiceUrl;

    public function __construct()
    {
        $this->secret                 = env('INTERNAL_SERVICE_SECRET', '');
        $this->contractServiceUrl     = env('CONTRACT_SERVICE_URL', 'http://contract-management:8000/api');
        $this->vendorServiceUrl       = env('VENDOR_SERVICE_URL', 'http://vendor-management:8000/api');
        $this->notificationServiceUrl = env('NOTIFICATION_SERVICE_URL', 'http://notification:8000/api');
        $this->aiServiceUrl           = env('AI_SERVICE_URL', 'http://ai-service:8000/api');
    }

    public function contractMetrics(): ?array
    {
        return $this->getJson("{$this->contractServiceUrl}/internal/metrics/contracts");
    }

    public function contractsBySegment(): ?array
    {
        return $this->getJson("{$this->contractServiceUrl}/internal/metrics/contracts/by-segment");
    }

    public function vendorMetrics(): ?array
    {
        return $this->getJson("{$this->vendorServiceUrl}/internal/metrics/vendors");
    }

    public function notificationMetrics(): ?array
    {
        return $this->getJson("{$this->notificationServiceUrl}/internal/metrics/notifications");
    }

    public function aiMetrics(): ?array
    {
        return $this->getJson("{$this->aiServiceUrl}/internal/metrics/ai");
    }

    protected function getJson(string $url): ?array
    {
        try {
            $response = Http::withHeaders([
                'Accept'            => 'application/json',
                'X-Internal-Secret' => $this->secret,
            ])->get($url);

            if (!$response->successful()) {
                Log::warning('MetricsClient: request failed', ['url' => $url, 'status' => $response->status()]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('MetricsClient: connection error', ['url' => $url, 'message' => $e->getMessage()]);
            return null;
        }
    }
}
