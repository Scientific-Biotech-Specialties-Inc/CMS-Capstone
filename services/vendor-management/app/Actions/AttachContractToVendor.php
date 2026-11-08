<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\ConflictException;
use App\Models\VendorContractAssociation;
use App\Payloads\AttachContractPayload;

final readonly class AttachContractToVendor
{
    public function handle(AttachContractPayload $payload): VendorContractAssociation
    {
        // Block if this contract is already linked to ANY vendor (globally unique)
        $takenGlobally = VendorContractAssociation::where('contract_id', $payload->contractId)->exists();

        if ($takenGlobally) {
            throw new ConflictException('This contract is already linked to another vendor.');
        }

        $exists = VendorContractAssociation::where([
            'vendor_type' => $payload->vendorType,
            'vendor_id'   => $payload->vendorId,
            'contract_id' => $payload->contractId,
        ])->exists();

        if ($exists) {
            throw new ConflictException('Contract already linked to this vendor.');
        }

        return VendorContractAssociation::create([
            'vendor_type' => $payload->vendorType,
            'vendor_id'   => $payload->vendorId,
            'contract_id' => $payload->contractId,
            'attached_by' => $payload->attachedBy,
        ]);
    }
}
