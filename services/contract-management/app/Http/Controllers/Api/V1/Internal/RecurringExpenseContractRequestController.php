<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractApprovalStatus;
use App\Models\ContractCategory;
use App\Models\ContractStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * SERMS Integration (1.3): Converts a recurring-expense purchase history
 * summary forwarded by SERMS Accounting into a pending CMS Contract Request.
 */
final class RecurringExpenseContractRequestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vendor_name'         => 'required|string|max:255',
            'tin_number'          => 'nullable|string|max:100',
            'service_description' => 'required|string|max:2000',
            'category'            => 'nullable|string|max:255',
            'region'              => 'nullable|string|max:100',
            'occurrence_count'    => 'required|integer|min:1',
            'total_spend'         => 'required|numeric|min:0',
            'period_start'        => 'required|date',
            'period_end'          => 'required|date',
            'source_reference_id' => 'nullable|string|max:100',
            'forwarded_by'        => 'nullable|integer',
            'expense_items'       => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $category = ContractCategory::query()
            ->when(!empty($data['category']), fn ($q) => $q->where('category_name', $data['category']))
            ->first()
            ?? ContractCategory::firstOrCreate(['category_name' => $data['category'] ?? 'Service Agreement']);

        $pendingApproval = ContractApprovalStatus::firstOrCreate(['status_name' => 'Pending']);
        $initialStatus = ContractStatus::firstOrCreate(['status_name' => 'Draft']);

        $regionName = $data['region'] ?? 'Luzon';
        $regionId = DB::table('contract_regions')->where('region_name', $regionName)->value('region_id')
            ?? DB::table('contract_regions')->value('region_id');

        $summary = [
            'vendor_name'      => $data['vendor_name'],
            'tin_number'       => $data['tin_number'] ?? null,
            'occurrence_count' => (int) $data['occurrence_count'],
            'total_spend'      => (float) $data['total_spend'],
            'period_start'     => $data['period_start'],
            'period_end'       => $data['period_end'],
            'expense_items'    => $data['expense_items'] ?? [],
        ];

        $contract = Contract::create([
            'category_id'                => $category->category_id,
            'approval_status_id'         => $pendingApproval->approval_status_id,
            'workflow_status_id'         => $initialStatus->status_id,
            'region_id'                  => $regionId,
            'bp_name'                    => $data['vendor_name'],
            'item_code'                  => 'SERMS-REC',
            'sbu_number'                 => 'SERMS',
            'serial_number'              => 'SERMS-' . strtoupper(substr(md5((string) microtime(true)), 0, 8)),
            'description'                => $data['service_description'],
            'contract_value'             => (float) $data['total_spend'],
            'start_date'                 => now()->toDateString(),
            'end_date'                   => now()->addYear()->toDateString(),
            'created_by'                 => $data['forwarded_by'] ?? null,
            'source_system'              => 'SERMS',
            'source_reference_id'        => $data['source_reference_id'] ?? null,
            'supporting_expense_summary' => $summary,
        ]);

        return response()->json([
            'data' => [
                'contract_id'                => $contract->contract_id,
                'contract_code'              => $contract->contract_code,
                'client_name'                => $contract->bp_name,
                'contract_value'             => (float) $contract->contract_value,
                'source_system'              => $contract->source_system,
                'source_reference_id'        => $contract->source_reference_id,
                'supporting_expense_summary' => $contract->supporting_expense_summary,
                'approval_status'            => $pendingApproval->status_name,
            ],
        ], 201);
    }
}
