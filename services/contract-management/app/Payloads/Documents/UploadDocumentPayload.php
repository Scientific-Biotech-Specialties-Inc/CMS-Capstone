<?php

declare(strict_types=1);

namespace App\Payloads\Documents;

use Illuminate\Http\UploadedFile;

final readonly class UploadDocumentPayload
{
    public function __construct(
        public UploadedFile $file,
        public ?int $contractId
    ) {}
}
