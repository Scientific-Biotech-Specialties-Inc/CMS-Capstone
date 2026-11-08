<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\PartnerContracts;

use App\Actions\AttachContractToVendor;
use App\Exceptions\ConflictException;
use App\Http\Requests\AttachContractRequest;
use App\Http\Resources\VendorContractResource;
use Illuminate\Http\JsonResponse;

final class StoreController
{
    public function __invoke(
        AttachContractRequest $request,
        AttachContractToVendor $action,
        int $id
    ): JsonResponse {
        try {
            $attachedBy = (int) $request->get('auth_id');
            $payload = $request->toPayload('partner', $id, $attachedBy);
            $association = $action->handle($payload);

            return (new VendorContractResource($association->load('contract')))
                ->response()
                ->setStatusCode(201);
        } catch (ConflictException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
