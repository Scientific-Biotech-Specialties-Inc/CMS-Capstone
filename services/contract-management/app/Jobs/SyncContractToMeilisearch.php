<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MeilisearchService;
use App\Http\Controllers\ContractController;
use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SyncContractToMeilisearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly array|int $contract) {}

    public function handle(MeilisearchService $meili): void
    {
        $id = is_array($this->contract) ? ($this->contract['contract_db_id'] ?? $this->contract['contract_id']) : $this->contract;
        $contract = Contract::with(['category', 'approvalStatus', 'workflowStatus', 'region'])->find($id);
        if ($contract) {
            $meili->upsertContract(ContractController::formatContract($contract));
        }
    }
}
