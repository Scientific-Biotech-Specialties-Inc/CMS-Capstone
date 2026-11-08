<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessPartner;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IntegrationCustomerController extends Controller
{
    /**
     * Display a paginated list of active customers (Business Partners with existing contracts).
     */
    public function index(Request $request): JsonResponse
    {
        $query = BusinessPartner::query()
            ->where('status', 'Active')
            ->whereHas('associations.contract');

        $perPage = min((int) $request->input('per_page', 15), 100);
        $customers = $query->paginate($perPage);

        return response()->json($customers);
    }
}
