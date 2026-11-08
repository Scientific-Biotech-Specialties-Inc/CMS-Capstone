<?php

namespace App\Http\Controllers;

use App\Models\ContractApprovalStatus;
use App\Models\ContractCategory;
use App\Models\ContractRegion;
use App\Models\ContractStatus;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    /**
     * Return lookup values for a given type.
     *
     * Supported types:
     *   categories        — contract category names
     *   approval-statuses — Pending | Approved | Rejected
     *   workflow-statuses — Notarized PDF | Client Review | SBSI Review (with color codes)
     *   regions           — Luzon | Visayas | Mindanao
     */
    public function show(string $type): JsonResponse
    {
        return match ($type) {
            'categories' => response()->json([
                'data' => ContractCategory::where('is_active', true)
                    ->orderBy('category_name')
                    ->pluck('category_name'),
            ]),

            'approval-statuses' => response()->json([
                'data' => ContractApprovalStatus::orderBy('status_name')->pluck('status_name'),
            ]),

            'workflow-statuses' => response()->json([
                'data' => ContractStatus::orderBy('status_name')
                    ->select('status_name', 'color_code')
                    ->get(),
            ]),

            'regions' => response()->json([
                'data' => ContractRegion::orderBy('region_name')->pluck('region_name'),
            ]),

            default => response()->json([
                'message' => "Unknown lookup type: '{$type}'.",
            ], 404),
        };
    }
}
