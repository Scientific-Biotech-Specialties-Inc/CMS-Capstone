<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\BusinessPartner;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

final class VendorEligibilityController
{
    public function __invoke(string $code): JsonResponse
    {
        $parts = explode('-', $code, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[1])) {
            return response()->json(['message' => 'Invalid vendor code.'], 404);
        }

        [$prefix, $idPart] = $parts;
        $id = (int) $idPart;

        if (strtoupper($prefix) === 'BP') {
            $vendor = BusinessPartner::find($id);
            $type = 'partner';
            $name = $vendor?->partner_name;
        } elseif (strtoupper($prefix) === 'SP') {
            $vendor = Supplier::find($id);
            $type = 'supplier';
            $name = $vendor?->supplier_name;
        } else {
            return response()->json(['message' => 'Invalid vendor code.'], 404);
        }

        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found.'], 404);
        }

        return response()->json([
            'data' => [
                'code'        => strtoupper($prefix) . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT),
                'type'        => $type,
                'name'        => $name,
                'status'      => $vendor->status,
                'is_eligible' => $vendor->status === 'Active',
            ],
        ]);
    }
}
