<?php

namespace App\Jobs;

use App\Services\RiskAssessment\RiskAssessmentPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * US-026: runs the RAG risk-assessment pipeline asynchronously (Gemini calls
 * add real latency, so this is queued rather than run inline on the
 * triggering request — QUEUE_CONNECTION=database is already configured).
 */
class RunRiskAssessmentPipeline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(public int $contractId)
    {
    }

    public function handle(RiskAssessmentPipeline $pipeline): void
    {
        try {
            $pipeline->run($this->contractId);
        } catch (\Exception $e) {
            Log::error('Risk assessment pipeline job failed.', [
                'contract_id' => $this->contractId,
                'message'     => $e->getMessage(),
            ]);
        }
    }
}
