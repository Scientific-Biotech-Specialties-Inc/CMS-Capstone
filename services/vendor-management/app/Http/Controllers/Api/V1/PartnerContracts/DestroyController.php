<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\PartnerContracts;

use App\Actions\DetachContractFromVendor;
use Illuminate\Http\JsonResponse;

final class DestroyController
{
    public function __invoke(
        DetachContractFromVendor $action,
        int $id,
        int $contractId
    ): JsonResponse {
        $action->handle('partner', $id, $contractId);

        return response()->json([
            'message' => 'Contract detached successfully.',
        ], 200);
    }
}
