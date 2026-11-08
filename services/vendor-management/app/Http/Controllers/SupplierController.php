<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\DuplicateDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
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
        $query = Supplier::query()->withCount('associations');

        if ($request->filled('region') && $request->region !== 'All') {
            $query->where('region', $request->region);
        }

        if ($request->filled('search')) {
            $search = mb_substr((string) $request->query('search'), 0, 255);
            $query->where(function ($q) use ($search) {
                $q->where('supplier_name', 'like', '%' . $search . '%')
                  ->orWhere('industry', 'like', '%' . $search . '%');
            });
        } elseif ($request->filled('supplier_name')) {
            $supplierName = mb_substr((string) $request->supplier_name, 0, 255);
            $query->where('supplier_name', 'like', $supplierName . '%');
        }

        if ($request->filled('industry') && $request->industry !== 'All') {
            $query->where('industry', $request->industry);
        }

        if ($request->filled('status') && $request->status !== 'All') {
            $query->where('status', $request->status);
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        // Newest first so a freshly created supplier always lands on page 1.
        return response()->json($query->orderByDesc('supplier_id')->paginate($perPage));
    }

    /**
     * Distinct industry values, used to populate the Industry filter.
     */
    public function industries()
    {
        $industries = Supplier::query()
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
            'supplier_name'  => 'required|string|max:255',
            'tin_number'     => 'required|string|max:100|regex:/^\d{3}-\d{3}-\d{3}(-\d{3,5})?$/',
            'industry'       => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string',
            'email'          => 'nullable|email|max:255',
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

        // Run duplicate detection
        $detection = $this->duplicateDetectionService->detect(
            'suppliers',
            'tin_number',
            $data['tin_number'] ?? null,
            'supplier_name',
            $data['supplier_name'],
            null,
            'supplier_id'
        );

        if ($detection['exact_duplicate']) {
            return response()->json([
                'message' => 'A supplier with this TIN number already exists.'
            ], 409);
        }

        $supplier = Supplier::create($data);

        // Audit Log
        $userId = $request->get('auth_id');
        $this->auditLogService->log('created', 'Supplier', $supplier->supplier_id, $userId, [], $supplier->toArray(), $request->get('auth_department'));

        $warnings = [];
        if (!empty($detection['fuzzy_warnings'])) {
            $warnings[] = [
                'type' => 'fuzzy_name_match',
                'message' => 'Similar supplier names found. Please confirm this is a new record.',
                'matches' => $detection['fuzzy_warnings']
            ];
        }

        try {
            app(\App\Services\NotificationService::class)->push(
                null,
                'new_vendor',
                "A new supplier '{$supplier->supplier_name}' has been added to the system.",
                'Employee,Sales,Manager,Admin'
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to push supplier notification: " . $e->getMessage());
        }

        return response()->json([
            'data' => $supplier,
            'warnings' => $warnings
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }

        return response()->json(['data' => $supplier]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'supplier_name'  => 'required|string|max:255',
            'tin_number'     => 'required|string|max:100|regex:/^\d{3}-\d{3}-\d{3}(-\d{3,5})?$/',
            'industry'       => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string',
            'email'          => 'nullable|email|max:255',
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
            'suppliers',
            'tin_number',
            $data['tin_number'] ?? null,
            'supplier_name',
            $data['supplier_name'],
            $id,
            'supplier_id'
        );

        if ($detection['exact_duplicate']) {
            return response()->json([
                'message' => 'A supplier with this TIN number already exists.'
            ], 409);
        }

        $oldData = $supplier->toArray();
        $supplier->update($data);

        // Audit Log
        $userId = $request->get('auth_id');
        $this->auditLogService->log('updated', 'Supplier', $supplier->supplier_id, $userId, $oldData, $supplier->toArray(), $request->get('auth_department'));

        $warnings = [];
        if (!empty($detection['fuzzy_warnings'])) {
            $warnings[] = [
                'type' => 'fuzzy_name_match',
                'message' => 'Similar supplier names found. Please confirm if this is a duplicate.',
                'matches' => $detection['fuzzy_warnings']
            ];
        }

        return response()->json([
            'data' => $supplier,
            'warnings' => $warnings
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }

        $oldData = $supplier->toArray();
        $supplier->delete();

        // Audit Log
        $userId = $request->get('auth_id');
        $this->auditLogService->log('deleted', 'Supplier', $id, $userId, $oldData, [], $request->get('auth_department'));

        return response()->json(['message' => 'Supplier deleted successfully.']);
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

        $suppliers = Supplier::where('supplier_name', 'like', $q . '%')
            ->limit(10)
            ->get();

        return response()->json($suppliers);
    }
}
