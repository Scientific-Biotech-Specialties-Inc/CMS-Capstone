<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Document;
use App\Services\MalwareScannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class ScanUploadedDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        private readonly string $documentId,
        private readonly string $storagePath,
    ) {
        $this->onQueue('contract_queue');
    }

    public function handle(MalwareScannerService $scanner): void
    {
        $document = Document::find($this->documentId);
        if (!$document) {
            Log::warning("ScanJob: Document {$this->documentId} not found.");
            return;
        }

        // Check env toggle - ClamAV disabled entirely
        if (!config('clamav.enabled', true)) {
            $document->update(['scan_status' => 'skipped']);
            Log::info("ScanJob: CLAMAV_ENABLED=false, document {$this->documentId} marked skipped.");
            return;
        }

        // Retrieve file from storage for scanning
        $disk = config('filesystems.default', 'local');
        $contents = Storage::disk($disk)->get($this->storagePath);
        if (!$contents) {
            $document->update(['scan_status' => 'unavailable']);
            Log::warning("ScanJob: Could not retrieve file contents from storage for {$this->storagePath}.");
            return;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'clam_');
        if ($tempPath === false) {
            $document->update(['scan_status' => 'unavailable']);
            Log::error("ScanJob: Failed to create temp file.");
            return;
        }

        file_put_contents($tempPath, $contents);

        try {
            $result = $scanner->scanPath($tempPath, basename($this->storagePath));
        } finally {
            @unlink($tempPath);
        }

        if ($result->isInfected) {
            Storage::disk($disk)->delete($this->storagePath);
            $document->update([
                'scan_status'  => 'infected',
                'scan_result'  => $result->virusName,
                'file_path'    => null,
                'document_url' => null,
            ]);
            Log::error("ScanJob: MALWARE in document {$this->documentId}: {$result->virusName}");
            return;
        }

        $status = $result->warning ? 'unavailable' : 'clean';
        $document->update(['scan_status' => $status]);
        Log::info("ScanJob: document {$this->documentId} status set to {$status}");
    }
}
