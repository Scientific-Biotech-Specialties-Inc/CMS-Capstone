<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Documents;

use App\Actions\Documents\UploadDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\UploadDocumentRequest;
use App\Http\Resources\DocumentResource;
use Illuminate\Http\JsonResponse;

final class UploadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        UploadDocumentRequest $request,
        UploadDocument $action
    ): JsonResponse {
        $result = $action->handle($request->toPayload());

        return response()->json([
            'message'     => 'Document uploaded. Antivirus scan queued.',
            'data'        => new DocumentResource($result->document),
            'scan_status' => 'pending',
        ], 201);
    }
}
