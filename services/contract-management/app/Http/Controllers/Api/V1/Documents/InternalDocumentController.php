<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Internal, X-Internal-Secret-authenticated endpoints used by ai-service's
 * RAG risk-assessment pipeline (Feature 1) to fetch a contract's documents
 * and raw file bytes for text extraction. No user token needed — this is
 * service-to-service only, matching the pattern already used for
 * /internal/audit-event and other services' internal.secret routes.
 */
final class InternalDocumentController extends Controller
{
    /**
     * GET /internal/contracts/{contractId}/documents
     */
    public function listForContract(Request $request, $contractId)
    {
        $numericId = is_numeric($contractId) ? (int) $contractId : null;
        if (!$numericId) {
            $contract = \App\Models\Contract::where('contract_code', $contractId)->first();
            $numericId = $contract?->contract_id;
        }

        if (!$numericId) {
            return response()->json(['data' => []]);
        }

        $documents = Document::where('contract_id', $numericId)
            ->whereIn('scan_status', ['clean', 'unavailable'])
            ->get(['_id', 'file_name', 'file_type']);

        return response()->json([
            'data' => $documents->map(fn ($d) => [
                'document_id' => (string) $d->getKey(),
                'file_name'   => $d->file_name,
                'file_type'   => $d->file_type,
            ])->values(),
        ]);
    }

    /**
     * GET /internal/documents/{id}/file — streams the raw file bytes.
     */
    public function file(Request $request, string $id)
    {
        $document = Document::find($id);
        if (!$document) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        // file_path is encrypted in the DB; reconstruct the path from the uuid
        // which is stored plaintext and matches the actual filename on disk.
        $disk = 'public';
        $path = "contracts/documents/{$document->uuid}.{$document->file_type}";

        if (!Storage::disk($disk)->exists($path)) {
            return response()->json(['message' => 'File not found on storage disk.'], 404);
        }

        return Storage::disk($disk)->response($path, $document->file_name);
    }

    /**
     * GET /internal/documents/{id} — fetches document metadata.
     */
    public function show(Request $request, string $id)
    {
        $document = Document::find($id);
        if (!$document) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        $contractCreatedBy = null;
        if ($document->contract_id !== null) {
            $contractCreatedBy = \App\Models\Contract::where('contract_id', $document->contract_id)->value('created_by');
        }

        return response()->json([
            'data' => [
                'document_id'         => (string) $document->getKey(),
                'file_name'           => $document->file_name,
                'file_type'           => $document->file_type,
                'contract_id'         => $document->contract_id,
                'uploaded_by'         => $document->uploaded_by,
                'contract_created_by' => $contractCreatedBy,
            ],
        ]);
    }
}
