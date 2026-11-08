<?php

namespace App\Jobs;

use App\Models\VendorSuggestion;
use App\Services\VendorSuggestion\VendorSuggestionPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Feature 3: runs the Vendor AI Suggestion pipeline asynchronously (Gemini
 * calls add real latency, matching the same queued pattern as Feature 1's
 * RunRiskAssessmentPipeline).
 */
class RunVendorSuggestionPipeline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(public int $vendorSuggestionId)
    {
    }

    public function handle(VendorSuggestionPipeline $pipeline): void
    {
        $suggestion = VendorSuggestion::find($this->vendorSuggestionId);
        if (!$suggestion) {
            return;
        }

        try {
            $pipeline->run($suggestion);
        } catch (\Exception $e) {
            Log::error('Vendor suggestion pipeline job failed.', [
                'vendor_suggestion_id' => $this->vendorSuggestionId,
                'message'              => $e->getMessage(),
            ]);
            $suggestion->update(['status' => 'failed']);
        }
    }
}
