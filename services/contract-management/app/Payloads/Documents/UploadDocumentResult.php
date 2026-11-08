<?php

declare(strict_types=1);

namespace App\Payloads\Documents;

use App\Models\Document;

final readonly class UploadDocumentResult
{
    public function __construct(
        public Document $document,
        public ?string $scanWarning = null,
    ) {}
}
