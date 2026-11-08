<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\Document;
use App\Payloads\Documents\UploadDocumentPayload;
use App\Payloads\Documents\UploadDocumentResult;
use App\Services\AuditLogService;
use App\Jobs\ScanUploadedDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final readonly class UploadDocument
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Handle the document upload logic.
     *
     * @throws ValidationException
     */
    public function handle(UploadDocumentPayload $payload): UploadDocumentResult
    {
        // 1. Generate UUID and store file using default filesystem disk (object storage abstraction)
        $disk = config('filesystems.default', 'local');
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $extension = strtolower($payload->file->getClientOriginalExtension());
        $filename = "{$uuid}.{$extension}";
        
        $path = Storage::disk($disk)->putFileAs('contracts/documents', $payload->file, $filename);

        if (!$path) {
            throw ValidationException::withMessages([
                'file' => ['Failed to save the file to object storage.'],
            ]);
        }

        // Generate accessible URL
        $url = Storage::disk($disk)->url($path);

        $uploadedBy = request()->get('auth_id');

        // 2. Store metadata record in MongoDB
        $document = Document::create([
            'contract_id' => $payload->contractId,
            'uuid' => $uuid,
            'file_name' => $payload->file->getClientOriginalName(),
            'file_path' => $path,
            'document_url' => $url,
            'file_type' => $extension,
            'file_size' => $payload->file->getSize(),
            'uploaded_by' => $uploadedBy,
            'uploaded_at' => now(),
            'scan_status' => 'pending',
        ]);

        // 3. Dispatch async ClamAV scan job
        ScanUploadedDocument::dispatch((string) $document->getKey(), $path);

        // 4. Audit Log Hook on Document Upload
        $this->auditLogService->log(
            'document_uploaded',
            'Document',
            (string) $document->getKey(),
            $uploadedBy,
            [],
            $document->toArray()
        );

        return new UploadDocumentResult($document, null);
    }
}
