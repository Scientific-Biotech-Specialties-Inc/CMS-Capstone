<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\VendorContractAssociation;
use Illuminate\Http\JsonResponse;

/**
 * Returns all contract IDs that are already linked to any vendor (partner or supplier).
 * Used by the frontend AssociateContractModal to disable already-taken contracts.
 */
final class LinkedContractIdsController
{
    public function __invoke(): JsonResponse
    {
        $ids = VendorContractAssociation::query()
            ->distinct()
            ->pluck('contract_id')
            ->map(fn ($id) => (string) $id)
            ->values();

        return response()->json(['data' => $ids]);
    }
}
