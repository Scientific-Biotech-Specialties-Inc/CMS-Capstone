<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SupplierContracts;

use App\Http\Resources\VendorContractResource;
use App\Models\VendorContractAssociation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class IndexController
{
    public function __invoke(Request $request, int $id): AnonymousResourceCollection
    {
        $query = VendorContractAssociation::forVendor('supplier', $id)->with('contract');

        if ($request->get('auth_role') === 'Sales') {
            $query->attachedBy((int) $request->get('auth_id'));
        }

        return VendorContractResource::collection($query->get());
    }
}
