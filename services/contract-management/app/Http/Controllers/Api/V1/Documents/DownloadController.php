<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class DownloadController extends Controller
{
    /**
     * Sales/Employee may only download documents belonging to contracts they
     * created (mirrors ContractController::show/update). Documents not linked
     * to a contract fall back to the uploader, so pre-link uploads stay
     * downloadable by the person who uploaded them.
     */
    private function forbidUnlessOwned(Request $request, Document $document): ?JsonResponse
    {
        $role = $request->get('auth_role');

        if (!in_array($role, ['Sales', 'Employee'], true)) {
            return null;
        }

        $ownerId = null;
        if ($document->contract_id !== null) {
            $ownerId = Contract::where('contract_id', $document->contract_id)->value('created_by');
        }
        if ($ownerId === null) {
            $ownerId = $document->uploaded_by;
        }

        if ($ownerId === null || (int) $ownerId !== (int) $request->get('auth_id')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return null;
    }

    /**
     * Serve the document file.
     */
    public function show(Request $request, string $id)
    {
        $document = Document::findOrFail($id);

        if ($denied = $this->forbidUnlessOwned($request, $document)) {
            return $denied;
        }

        $disk = config('filesystems.default', 'local');

        if (!Storage::disk($disk)->exists($document->file_path)) {
            return response()->json(['message' => 'File not found on storage disk.'], 404);
        }

        return Storage::disk($disk)->response($document->file_path, $document->file_name);
    }

    /**
     * Generate a pre-signed URL for secure document download.
     */
    public function presignedUrl(Request $request, string $id)
    {
        $document = Document::findOrFail($id);

        if ($denied = $this->forbidUnlessOwned($request, $document)) {
            return $denied;
        }

        $disk = config('filesystems.default', 'local');

        if (!Storage::disk($disk)->exists($document->file_path)) {
            return response()->json(['message' => 'File not found on storage disk.'], 404);
        }

        // Generate pre-signed temporary URL valid for 15 minutes
        $expiration = now()->addMinutes(15);
        $url = Storage::disk($disk)->temporaryUrl($document->file_path, $expiration);

        return response()->json([
            'document_id' => (string) $document->getKey(),
            'file_name' => $document->file_name,
            'presigned_url' => $url,
            'expires_at' => $expiration->toISOString(),
        ]);
    }
}
