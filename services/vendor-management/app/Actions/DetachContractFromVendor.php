<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\VendorContractAssociation;

final readonly class DetachContractFromVendor
{
    public function handle(string $vendorType, int $vendorId, int $contractId): void
    {
        VendorContractAssociation::where([
            'vendor_type' => $vendorType,
            'vendor_id'   => $vendorId,
            'contract_id' => $contractId,
        ])->firstOrFail()->delete();
    }
}
