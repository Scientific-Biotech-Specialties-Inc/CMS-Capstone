<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\PartnerContracts;

use App\Http\Resources\VendorContractResource;
use App\Models\BusinessPartner;
use App\Models\VendorContractAssociation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class IndexController
{
    public function __invoke(Request $request, string $id): AnonymousResourceCollection
    {
        $partner = BusinessPartner::where('bp_code', $id)->orWhere('partner_id', $id)->first();
        $numericId = $partner ? (int) $partner->partner_id : (int) $id;

        $query = VendorContractAssociation::forVendor('partner', $numericId)->with('contract');

        if ($request->get('auth_role') === 'Sales') {
            $query->attachedBy((int) $request->get('auth_id'));
        }

        return VendorContractResource::collection($query->get());
    }
}
