<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\VendorReimbursements;

use App\Http\Resources\VendorReimbursementResource;
use App\Models\BusinessPartner;
use App\Models\Supplier;
use App\Services\SermsReimbursementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IndexController
{
    public function __invoke(Request $request, string $id, SermsReimbursementService $service): JsonResponse
    {
        $isPartner = str_contains($request->path(), 'partners/');

        if ($isPartner) {
            $cleanId = (string) preg_replace('/^(BP-)+/i', 'BP-', $id);
            $partner = BusinessPartner::where('bp_code', $cleanId)
                ->orWhere('bp_code', $id)
                ->orWhere('partner_id', is_numeric($id) ? (int) $id : 0)
                ->first();

            if (!$partner) {
                return response()->json(['message' => 'Business partner not found.'], 404);
            }

            $result = $service->syncForVendor('partner', (int) $partner->partner_id, null, (string) $partner->partner_name);
        } else {
            $supplier = Supplier::find(is_numeric($id) ? (int) $id : 0);
            if (!$supplier) {
                return response()->json(['message' => 'Supplier not found.'], 404);
            }

            $result = $service->syncForVendor('supplier', (int) $supplier->supplier_id, $supplier->tin_number, (string) $supplier->supplier_name);
        }

        $items = $result['items'];

        return response()->json([
            'data' => VendorReimbursementResource::collection($items),
            'meta' => [
                'serms_connected' => $result['serms_connected'],
                'count'           => $items->count(),
                'total_amount'    => round((float) $items->sum('amount'), 2),
                'synced_at'       => $result['synced_at'],
            ],
        ]);
    }
}
