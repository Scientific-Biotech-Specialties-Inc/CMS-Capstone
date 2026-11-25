<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class NotifyPrsOfContractUpdateJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 30;
    public int $tries = 2;

    public function __construct(
        private readonly int $prsActivityId,
        private readonly int $contractId,
        private readonly string $contractStatus,
        private readonly string $outcome
    ) {}

    public function uniqueId(): string
    {
        return "{$this->prsActivityId}:{$this->contractId}:{$this->outcome}";
    }

    public function handle(): void
    {
        $url = trim((string) config('services.prs.webhook_url', ''));
        if ($url === '') {
            $baseUrl = trim((string) config('services.prs.base_url', ''));
            $url = $baseUrl !== '' ? rtrim($baseUrl, '/') . '/api/integrations/cms/contract-outcome' : '';
        }

        if ($url === '') {
            Log::info('NotifyPrsOfContractUpdateJob skipped: PRS_WEBHOOK_URL and PRS_BASE_URL are not configured.', [
                'contract_id' => $this->contractId,
            ]);
            return;
        }

        $apiKey = (string) (config('services.prs.outbound_key') ?: config('services.prs.inbound_key') ?: '');

        // Fail closed when no secret is configured.
        if ($apiKey === '') {
            Log::warning('NotifyPrsOfContractUpdateJob skipped: PRS_INTEGRATION_KEY is not configured.', [
                'contract_id' => $this->contractId,
            ]);
            return;
        }

        $contractCode = Contract::where('contract_id', $this->contractId)->value('contract_code');

        try {
            $response = Http::connectTimeout(5)
                ->timeout(10)
                ->withToken($apiKey)
                ->withHeaders([
                    'Accept'                     => 'application/json',
                    'X-Integration-Secret'       => $apiKey,
                    'ngrok-skip-browser-warning' => 'true',
                ])
                ->post($url, [
                    'event_id'          => (string) Str::uuid(),
                    'source_system'     => 'CMS',
                    'prs_activity_id'   => $this->prsActivityId,
                    'activity_id'       => $this->prsActivityId,
                    'contract_id'       => $this->contractId,
                    'cms_contract_id'   => $this->contractId,
                    'cms_contract_code' => $contractCode,
                    'contract_status'   => $this->contractStatus,
                    'outcome'           => $this->outcome,
                    'decided_at'        => now()->toIso8601String(),
                ]);

            if ($response->failed()) {
                $status = $response->status();
                if ($status === 404 || $status === 502 || $status === 503 || ($status >= 520 && $status <= 530)) {
                    Log::warning('NotifyPrsOfContractUpdateJob skipped: PRS tunnel expired or unreachable.', [
                        'contract_id' => $this->contractId,
                        'status'      => $status,
                        'url'         => $url,
                    ]);
                    return;
                }
                Log::error('Failed to notify PRS of contract update. Status code: ' . $status . ' Body: ' . $response->body());
                throw new \Exception('PRS returned failed status code: ' . $status);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('NotifyPrsOfContractUpdateJob skipped: PRS unreachable (connection error / tunnel expired).', [
                'contract_id' => $this->contractId,
                'error'       => $e->getMessage(),
            ]);
            return;
        } catch (\Exception $e) {
            Log::error('Error in NotifyPrsOfContractUpdateJob: ' . $e->getMessage());
            throw $e;
        }
    }
}
