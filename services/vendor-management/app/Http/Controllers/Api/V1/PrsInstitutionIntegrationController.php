<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessPartner;
use App\Models\PartnerBranch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class PrsInstitutionIntegrationController extends Controller
{
    /**
     * GET /api/integrations/v1/prs/institutions
     * Returns institutions in PRS `institution_list` compatible format (without lat/lng).
     */
    public function institutions(Request $request): JsonResponse
    {
        $request->validate([
            'per_page'         => 'sometimes|integer|min:1|max:100',
            'include_inactive' => 'sometimes',
            'class_tier'       => 'sometimes|string|max:20',
            'updated_since'    => 'sometimes|string|max:64',
        ]);

        $includeInactive = filter_var($request->query('include_inactive', false), FILTER_VALIDATE_BOOLEAN);
        $query = BusinessPartner::query()->with('branches')->orderBy('partner_id');

        if (!$includeInactive) {
            $query->where('status', 'Active');
        }

        if ($classTier = BusinessPartner::normalizeClassTier($request->query('class_tier'))) {
            $query->where('class_tier', $classTier);
        }

        if ($updatedSince = trim((string) $request->query('updated_since', ''))) {
            try {
                $query->where('updated_at', '>=', Carbon::parse($updatedSince));
            } catch (Throwable) {
                // Ignore invalid timestamp filter to avoid breaking sync polling.
            }
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $term = "%{$search}%";
            $query->where(function ($q) use ($term): void {
                $q->where('partner_name', 'like', $term)
                    ->orWhere('bp_code', 'like', $term);
            });
        }

        $perPage = max(1, min($request->integer('per_page', 50), 100));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (BusinessPartner $partner): array => $partner->toPrsInstitutionPayload())
                ->values(),
            'meta' => [
                'current_page'    => $paginator->currentPage(),
                'last_page'       => $paginator->lastPage(),
                'per_page'        => $paginator->perPage(),
                'total'           => $paginator->total(),
                'source_of_truth' => 'CMS',
                'synced_at'       => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/integrations/v1/prs/locations
     * Returns all institution branch locations linked to Business Partners.
     */
    public function locations(Request $request): JsonResponse
    {
        $request->validate([
            'per_page'         => 'sometimes|integer|min:1|max:100',
            'include_inactive' => 'sometimes',
            'institution_uid'  => 'sometimes|string|max:100',
        ]);

        $includeInactive = filter_var($request->query('include_inactive', false), FILTER_VALIDATE_BOOLEAN);
        $query = PartnerBranch::query()->with('partner')->orderBy('branch_id');

        if (!$includeInactive) {
            $query->where('is_active', true);
        }

        if ($institutionUid = trim((string) $request->query('institution_uid', ''))) {
            $query->whereHas('partner', fn ($q) => $q->where('bp_code', $institutionUid));
        }

        $perPage = max(1, min($request->integer('per_page', 50), 100));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (PartnerBranch $branch): array => $branch->toPrsLocationPayload())
                ->values(),
            'meta' => [
                'current_page'    => $paginator->currentPage(),
                'last_page'       => $paginator->lastPage(),
                'per_page'        => $paginator->perPage(),
                'total'           => $paginator->total(),
                'source_of_truth' => 'CMS',
                'synced_at'       => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/integrations/v1/prs/institutions/sync
     * Links PRS institution_list IDs / branches or upserts institutions & branches idempotently.
     */
    public function sync(Request $request): JsonResponse
    {
        $items = $request->has('institutions')
            ? (array) $request->input('institutions')
            : [$request->all()];

        if ($items === []) {
            return response()->json(['message' => 'No institution records provided.'], 422);
        }

        $synced = [];

        foreach ($items as $index => $rawItem) {
            if (!is_array($rawItem)) {
                return response()->json(['message' => "Invalid institution payload at index {$index}."], 422);
            }

            if (isset($rawItem['class_tier'])) {
                $normalizedTier = BusinessPartner::normalizeClassTier((string) $rawItem['class_tier']);
                if ($normalizedTier !== null) {
                    $rawItem['class_tier'] = $normalizedTier;
                }
            }

            $validator = Validator::make($rawItem, [
                'prs_institution_id' => 'nullable|integer|min:1',
                'id'                 => 'nullable|integer|min:1',
                'bp_code'            => 'nullable|string|max:100',
                'institution_uid'    => 'nullable|string|max:100',
                'institution_name'   => 'required|string|max:255',
                'address'            => 'required|string|max:255',
                'class_tier'         => 'required|string|in:Class A,Class B,Class C',
                'is_active'          => 'nullable|boolean',
                'status'             => 'nullable|string|in:Active,Inactive,Suspended',
                'region'             => 'nullable|string|max:100',
                'email'              => 'nullable|email|max:255',
                'contact_person'     => 'nullable|string|max:255',
                'contact_number'     => 'nullable|string|max:100',
                'branches'           => 'nullable|array',
                'branches.*.prs_branch_id' => 'nullable|integer|min:1',
                'branches.*.branch_name'   => 'required_with:branches|string|max:255',
                'branches.*.address'       => 'required_with:branches|string|max:255',
                'branches.*.region'        => 'nullable|string|max:100',
                'branches.*.sbsi_branch'   => 'nullable|string|max:100',
                'branches.*.is_primary'    => 'nullable|boolean',
                'branches.*.is_active'     => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $prsInstId = $data['prs_institution_id'] ?? $data['id'] ?? null;
            $bpCode = $data['institution_uid'] ?? $data['bp_code'] ?? null;

            $partner = DB::transaction(function () use ($data, $prsInstId, $bpCode): BusinessPartner {
                $existing = null;
                if ($prsInstId !== null) {
                    $existing = BusinessPartner::where('prs_institution_id', $prsInstId)->first();
                }
                if (!$existing && $bpCode !== null) {
                    $existing = BusinessPartner::where('bp_code', $bpCode)->first();
                }
                if (!$existing) {
                    $existing = BusinessPartner::where('partner_name', $data['institution_name'])->first();
                }

                $status = $data['status']
                    ?? (array_key_exists('is_active', $data) ? ($data['is_active'] ? 'Active' : 'Inactive') : 'Active');

                $attributes = [
                    'prs_institution_id' => $prsInstId ?? $existing?->prs_institution_id,
                    'partner_name'       => $data['institution_name'],
                    'address'            => $data['address'],
                    'class_tier'         => $data['class_tier'],
                    'status'             => $status,
                    'region'             => $data['region'] ?? $existing?->region ?? 'Luzon',
                    'email'              => $data['email'] ?? $existing?->email,
                    'contact_person'     => $data['contact_person'] ?? $existing?->contact_person,
                    'contact_number'     => $data['contact_number'] ?? $existing?->contact_number,
                ];

                if ($existing) {
                    $existing->update($attributes);
                    $partner = $existing->refresh();
                } else {
                    $generatedCode = $bpCode ?: ('BP-' . str_pad((string) ((int) BusinessPartner::max('partner_id') + 1), 4, '0', STR_PAD_LEFT));
                    while (BusinessPartner::where('bp_code', $generatedCode)->exists()) {
                        $generatedCode = 'BP-' . random_int(10000, 99999);
                    }
                    $partner = BusinessPartner::create([
                        ...$attributes,
                        'bp_code' => $generatedCode,
                    ]);
                }

                // Ensure primary branch is synced
                $primary = PartnerBranch::where('partner_id', $partner->partner_id)
                    ->where('is_primary', true)
                    ->first();

                if ($primary) {
                    $primary->update([
                        'address'   => $data['address'],
                        'region'    => $partner->region,
                        'is_active' => $partner->status === 'Active',
                    ]);
                } else {
                    PartnerBranch::create([
                        'location_uid' => 'LOC-' . str_pad((string) $partner->partner_id, 4, '0', STR_PAD_LEFT),
                        'partner_id'   => $partner->partner_id,
                        'branch_name'  => 'Main Branch',
                        'address'      => $data['address'],
                        'region'       => $partner->region,
                        'sbsi_branch'  => 'Main Office',
                        'is_primary'   => true,
                        'is_active'    => $partner->status === 'Active',
                    ]);
                }

                // Sync additional branches if provided
                foreach (($data['branches'] ?? []) as $branchData) {
                    $prsBranchId = $branchData['prs_branch_id'] ?? null;
                    $branch = $prsBranchId
                        ? PartnerBranch::where('prs_branch_id', $prsBranchId)->first()
                        : PartnerBranch::where('partner_id', $partner->partner_id)
                            ->where('branch_name', $branchData['branch_name'])
                            ->first();

                    if ($branch) {
                        $branch->update([
                            'prs_branch_id' => $prsBranchId ?? $branch->prs_branch_id,
                            'branch_name'   => $branchData['branch_name'],
                            'address'       => $branchData['address'],
                            'region'        => $branchData['region'] ?? $partner->region,
                            'sbsi_branch'   => $branchData['sbsi_branch'] ?? $branch->sbsi_branch ?? 'Main Office',
                            'is_primary'    => (bool) ($branchData['is_primary'] ?? $branch->is_primary),
                            'is_active'     => (bool) ($branchData['is_active'] ?? true),
                        ]);
                    } else {
                        PartnerBranch::create([
                            'location_uid'  => 'LOC-' . $partner->partner_id . '-' . strtoupper(substr(md5($branchData['branch_name'] . microtime(true)), 0, 4)),
                            'partner_id'    => $partner->partner_id,
                            'prs_branch_id' => $prsBranchId,
                            'branch_name'   => $branchData['branch_name'],
                            'address'       => $branchData['address'],
                            'region'        => $branchData['region'] ?? $partner->region,
                            'sbsi_branch'   => $branchData['sbsi_branch'] ?? 'Main Office',
                            'is_primary'    => (bool) ($branchData['is_primary'] ?? false),
                            'is_active'     => (bool) ($branchData['is_active'] ?? true),
                        ]);
                    }
                }

                return $partner->load('branches');
            });

            $synced[] = $partner->toPrsInstitutionPayload();
        }

        return response()->json([
            'data'            => count($synced) === 1 ? $synced[0] : $synced,
            'count'           => count($synced),
            'source_of_truth' => 'CMS',
        ], 200);
    }
}
