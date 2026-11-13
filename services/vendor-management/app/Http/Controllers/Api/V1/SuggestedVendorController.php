<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SuggestedVendor;
use App\Models\Supplier;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestedVendorController extends Controller
{
    public function __construct(protected AuditLogService $auditLogService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'Pending');

        $query = SuggestedVendor::query()->orderByDesc('suggested_vendor_id');
        if ($status !== '' && $status !== 'All') {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $suggestion = SuggestedVendor::find($id);
        if (!$suggestion) {
            return response()->json(['message' => 'Suggested vendor not found.'], 404);
        }
        if ($suggestion->status !== 'Pending') {
            return response()->json(['message' => 'Suggested vendor has already been reviewed.'], 422);
        }

        $supplier = Supplier::create([
            'supplier_name'            => $suggestion->vendor_name,
            'tin_number'               => $suggestion->tin_number,
            'industry'                 => $suggestion->industry,
            'contact_person'           => $suggestion->contact_person,
            'contact_number'           => $suggestion->contact_number,
            'email'                    => $suggestion->email,
            'address'                  => $suggestion->address,
            'region'                   => $suggestion->region ?? 'Luzon',
            'status'                   => 'Active',
            'is_accredited'            => (bool) $request->input('is_accredited', true),
            'is_flagged'               => false,
            'default_expense_category' => $suggestion->default_expense_category,
        ]);

        $reviewerId = $request->get('auth_id') ? (int) $request->get('auth_id') : null;
        $suggestion->update([
            'status'              => 'Approved',
            'reviewed_by'         => $reviewerId,
            'reviewed_at'         => now(),
            'created_supplier_id' => $supplier->supplier_id,
        ]);

        $this->auditLogService->log(
            'created',
            'Supplier',
            $supplier->supplier_id,
            $reviewerId,
            [],
            $supplier->toArray(),
            $request->get('auth_department')
        );

        return response()->json([
            'data' => [
                'suggestion' => $suggestion->fresh(),
                'supplier'   => $supplier,
            ],
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $suggestion = SuggestedVendor::find($id);
        if (!$suggestion) {
            return response()->json(['message' => 'Suggested vendor not found.'], 404);
        }
        if ($suggestion->status !== 'Pending') {
            return response()->json(['message' => 'Suggested vendor has already been reviewed.'], 422);
        }

        $reviewerId = $request->get('auth_id') ? (int) $request->get('auth_id') : null;
        $suggestion->update([
            'status'           => 'Rejected',
            'reviewed_by'      => $reviewerId,
            'reviewed_at'      => now(),
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        return response()->json(['data' => $suggestion->fresh()]);
    }
}
