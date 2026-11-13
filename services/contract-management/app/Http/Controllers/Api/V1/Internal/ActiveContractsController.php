<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Provides active, approved contracts for external subsystems (e.g. SERMS
 * searchable contract/client dropdown via GET /api/contract/active) and
 * analytics margin calculations.
 */
final class ActiveContractsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $limit = min(max((int) $request->query('limit', 100), 1), 200);

        $contracts = Contract::active()
            ->with(['category', 'approvalStatus', 'workflowStatus'])
            ->orderByDesc('contract_id')
            ->get([
                'contract_id',
                'contract_code',
                'category_id',
                'approval_status_id',
                'workflow_status_id',
                'bp_name',
                'sbu_number',
                'item_code',
                'start_date',
                'end_date',
                'contract_value',
            ]);

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $contracts = $contracts->filter(static function (Contract $contract) use ($needle): bool {
                $fields = [
                    $contract->contract_code,
                    $contract->bp_name,
                    $contract->item_code,
                    $contract->sbu_number,
                ];

                foreach ($fields as $value) {
                    if ($value !== null && str_contains(mb_strtolower((string) $value), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        $data = $contracts->take($limit)->values()->map(static fn (Contract $contract): array => [
            'contract_id'    => $contract->contract_id,
            'contract_code'  => $contract->contract_code,
            'client_name'    => $contract->bp_name,
            'category'       => $contract->category?->category_name,
            'sbu_number'     => $contract->sbu_number,
            'item_code'      => $contract->item_code,
            'contract_value' => $contract->contract_value !== null ? (float) $contract->contract_value : null,
            'start_date'     => $contract->start_date?->toDateString(),
            'end_date'       => $contract->end_date?->toDateString(),
            'status'         => $contract->approvalStatus?->status_name ?? 'Approved',
        ]);

        return response()->json(['data' => $data]);
    }
}
