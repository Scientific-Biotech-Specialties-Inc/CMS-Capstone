<?php

namespace App\Http\Controllers;

use App\Models\BusinessPartner;
use App\Services\AuditLogService;
use App\Services\DuplicateDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusinessPartnerController extends Controller
{
    protected DuplicateDetectionService $duplicateDetectionService;
    protected AuditLogService $auditLogService;

    public function __construct(DuplicateDetectionService $duplicateDetectionService, AuditLogService $auditLogService)
    {
        $this->duplicateDetectionService = $duplicateDetectionService;
        $this->auditLogService = $auditLogService;
    }

    public function index(Request $request)
    {
        $query = BusinessPartner::query()->withCount('associations');

        if ($request->filled('region') && $request->region !== 'All') {
            $query->where('region', $request->region);
        }

        if ($request->filled('search')) {
            $search = mb_substr((string) $request->query('search'), 0, 255);
            $query->where(function ($q) use ($search) {
                $q->where('partner_name', 'like', '%' . $search . '%')
                  ->orWhere('industry', 'like', '%' . $search . '%');
            });
        } elseif ($request->filled('partner_name')) {
            $partnerName = mb_substr((string) $request->partner_name, 0, 255);
            $query->where('partner_name', 'like', $partnerName . '%');
        }

        if ($request->filled('industry') && $request->industry !== 'All') {
            $query->where('industry', $request->industry);
        }

        if ($request->filled('status') && $request->status !== 'All') {
            $query->where('status', $request->status);
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        // Newest first so a freshly created partner always lands on page 1.
        return response()->json($query->orderByDesc('partner_id')->paginate($perPage));
    }

    /**
     * Distinct industry values, used to populate the Industry filter.
     */
    public function industries()
    {
        $industries = BusinessPartner::query()
            ->whereNotNull('industry')
            ->where('industry', '!=', '')
            ->distinct()
            ->orderBy('industry')
            ->pluck('industry');

        return response()->json(['data' => $industries]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bp_code'        => 'required|string|max:100',
            'partner_name'   => 'required|string|max:255',
            'industry'       => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string',
            'email'          => 'required|email|max:255',
            'address'        => 'nullable|string',
            'region'         => 'nullable|string|max:100',
            'status'         => 'nullable|string|in:Active,Inactive,Suspended',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Normalise the industry so " Healthcare " cannot create a duplicate
        // entry in the Industry filter.
        if (array_key_exists('industry', $data)) {
            $industry = trim((string) $data['industry']);
            $data['industry'] = $industry === '' ? null : $industry;
        }

        $data['created_by'] = $request->get('auth_id');

        // Run duplicate detection
        $detection = $this->duplicateDetectionService->detect(
            'business_partners',
            'bp_code',
            $data['bp_code'],
            'partner_name',
            $data['partner_name'],
            null,
            'partner_id'
        );

        if ($detection['exact_duplicate']) {
            return response()->json([
                'message' => 'A business partner with this BP code already exists.'
            ], 409);
        }

        $partner = BusinessPartner::create($data);

        // Audit Log
        $userId = $request->get('auth_id');
        $this->auditLogService->log('created', 'BusinessPartner', $partner->partner_id, $userId, [], $partner->toArray(), $request->get('auth_department'));

        $warnings = [];
        if (!empty($detection['fuzzy_warnings'])) {
            $warnings[] = [
                'type' => 'fuzzy_name_match',
                'message' => 'Similar business partner names found. Please confirm this is a new record.',
                'matches' => $detection['fuzzy_warnings']
            ];
        }

        try {
            app(\App\Services\NotificationService::class)->push(
                null,
                'new_vendor',
                "A new business partner '{$partner->partner_name}' has been added to the system.",
                'Employee,Sales,Manager,Admin'
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to push business partner notification: " . $e->getMessage());
        }

        return response()->json([
            'data' => $partner,
            'warnings' => $warnings
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $cleanId = preg_replace('/^(BP-)+/i', 'BP-', (string) $id);
        $partner = BusinessPartner::where('bp_code', $cleanId)
            ->orWhere('bp_code', $id)
            ->orWhere('partner_id', $cleanId)
            ->orWhere('partner_id', $id)
            ->first();

        if (!$partner) {
            return response()->json(['message' => 'Business partner not found.'], 404);
        }

        return response()->json(['data' => $partner]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $cleanId = preg_replace('/^(BP-)+/i', 'BP-', (string) $id);
        $partner = BusinessPartner::where('bp_code', $cleanId)
            ->orWhere('bp_code', $id)
            ->orWhere('partner_id', $cleanId)
            ->orWhere('partner_id', $id)
            ->first();

        if (!$partner) {
            return response()->json(['message' => 'Business partner not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'bp_code'        => 'required|string|max:100',
            'partner_name'   => 'required|string|max:255',
            'industry'       => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string',
            'email'          => 'required|email|max:255',
            'address'        => 'nullable|string',
            'region'         => 'nullable|string|max:100',
            'status'         => 'nullable|string|in:Active,Inactive,Suspended',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Normalise the industry so " Healthcare " cannot create a duplicate
        // entry in the Industry filter.
        if (array_key_exists('industry', $data)) {
            $industry = trim((string) $data['industry']);
            $data['industry'] = $industry === '' ? null : $industry;
        }

        // Run duplicate detection (excluding self)
        $detection = $this->duplicateDetectionService->detect(
            'business_partners',
            'bp_code',
            $data['bp_code'],
            'partner_name',
            $data['partner_name'],
            $partner->partner_id,
            'partner_id'
        );

        if ($detection['exact_duplicate']) {
            return response()->json([
                'message' => 'A business partner with this BP code already exists.'
            ], 409);
        }

        $oldData = $partner->toArray();
        $partner->update($data);

        // Audit Log
        $userId = $request->get('auth_id');
        $this->auditLogService->log('updated', 'BusinessPartner', $partner->partner_id, $userId, $oldData, $partner->toArray(), $request->get('auth_department'));

        $warnings = [];
        if (!empty($detection['fuzzy_warnings'])) {
            $warnings[] = [
                'type' => 'fuzzy_name_match',
                'message' => 'Similar business partner names found. Please confirm if this is a duplicate.',
                'matches' => $detection['fuzzy_warnings']
            ];
        }

        return response()->json([
            'data' => $partner,
            'warnings' => $warnings
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $cleanId = preg_replace('/^(BP-)+/i', 'BP-', (string) $id);
        $partner = BusinessPartner::where('bp_code', $cleanId)
            ->orWhere('bp_code', $id)
            ->orWhere('partner_id', $cleanId)
            ->orWhere('partner_id', $id)
            ->first();

        if (!$partner) {
            return response()->json(['message' => 'Business partner not found.'], 404);
        }

        $partnerId = $partner->partner_id;
        $oldData = $partner->toArray();
        $partner->delete();

        // Audit Log
        $userId = $request->get('auth_id');
        $this->auditLogService->log('deleted', 'BusinessPartner', $partnerId, $userId, $oldData, [], $request->get('auth_department'));

        return response()->json(['message' => 'Business partner deleted successfully.']);
    }

    /**
     * Search resource with index lookup.
     */
    public function search(Request $request)
    {
        $q = $request->query('q', '');

        if ($q === '') {
            return response()->json([]);
        }

        $partners = BusinessPartner::where('partner_name', 'like', $q . '%')
            ->limit(10)
            ->get();

        return response()->json($partners);
    }
}
