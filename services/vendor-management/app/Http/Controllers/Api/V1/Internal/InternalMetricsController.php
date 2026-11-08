<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Models\BusinessPartner;
use App\Models\Supplier;
use Illuminate\Http\Request;

/**
 * Internal, X-Internal-Secret-authenticated metrics snapshot for
 * analytics-service's descriptive aggregation (Feature 4).
 */
class InternalMetricsController extends Controller
{
    public function vendors(Request $request)
    {
        return response()->json([
            'total_suppliers' => Supplier::count(),
            'total_partners'  => BusinessPartner::count(),
            'suppliers_by_region'   => Supplier::select('region')->get()->countBy('region'),
            'partners_by_region'    => BusinessPartner::select('region')->get()->countBy('region'),
            'suppliers_by_status'   => Supplier::select('status')->get()->countBy('status'),
            'partners_by_status'    => BusinessPartner::select('status')->get()->countBy('status'),
            'suppliers_by_industry' => Supplier::select('industry')->get()->countBy('industry'),
            'partners_by_industry'  => BusinessPartner::select('industry')->get()->countBy('industry'),
        ]);
    }
}
