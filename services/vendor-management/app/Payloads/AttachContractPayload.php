<?php

declare(strict_types=1);

namespace App\Payloads;

final readonly class AttachContractPayload
{
    public function __construct(
        public string $vendorType,
        public int $vendorId,
        public int $contractId,
        public int $attachedBy
    ) {}
}
