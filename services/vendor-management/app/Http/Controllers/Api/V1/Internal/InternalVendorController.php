<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Models\BusinessPartner;
use App\Models\SuggestedVendor;
use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\DuplicateDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Internal, X-Internal-Secret-authenticated endpoints used by ai-service's
 * Vendor AI Suggestions feature (Feature 3): listing existing vendors for
 * the embedding backfill, and creating a real supplier/partner row once a
 * suggested candidate is accepted. No user bearer token available in that
 * context (the accept action runs server-side in ai-service), so this
 * mirrors the internal.secret pattern already used by
 * InternalAuditController rather than requiring a forwarded user token.
 */
class InternalVendorController extends Controller
{
    public function __construct(
        protected DuplicateDetectionService $duplicateDetectionService,
        protected AuditLogService $auditLogService,
    ) {
    }

    /**
     * GET /internal/suppliers — paginated, same shape as the public endpoint,
     * for the embedding backfill.
     */
    public function suppliers(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 100), 200);
        return response()->json(Supplier::query()->paginate($perPage));
    }

    /**
     * GET /internal/partners
     */
    public function partners(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 100), 200);
        return response()->json(BusinessPartner::query()->paginate($perPage));
    }

    /**
     * POST /internal/suppliers — creates a supplier from an accepted Vendor
     * AI Suggestion candidate. Same validation/duplicate-detection as the
     * public SupplierController@store, minus the user-token-derived
     * auth_id/auth_department (attributed to the accepting admin, passed
     * explicitly by ai-service instead).
     */
    public function storeSupplier(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_name'  => 'required|string|max:255',
            'tin_number'     => 'nullable|string|max:100|regex:/^\d{3}-\d{3}-\d{3}(-\d{3,5})?$/',
            'industry'       => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string',
            'region'         => 'nullable|string|max:100',
            'status'         => 'nullable|string|in:Active,Inactive,Suspended',
            'decided_by'     => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $decidedBy = $data['decided_by'] ?? null;
        unset($data['decided_by']);

        if (!empty($data['tin_number'])) {
            $detection = $this->duplicateDetectionService->detect(
                'suppliers', 'tin_number', $data['tin_number'], 'supplier_name', $data['supplier_name'], null, 'supplier_id'
            );
            if ($detection['exact_duplicate']) {
                return response()->json(['message' => 'A supplier with this TIN number already exists.'], 409);
            }
        }

        $supplier = Supplier::create($data);

        $this->auditLogService->log('created', 'Supplier', $supplier->supplier_id, $decidedBy, [], $supplier->toArray(), null);

        return response()->json(['data' => $supplier], 201);
    }

    /**
     * POST /internal/partners
     */
    public function storePartner(Request $request)
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
            'decided_by'     => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $decidedBy = $data['decided_by'] ?? null;
        unset($data['decided_by']);
        $data['created_by'] = $decidedBy;

        $detection = $this->duplicateDetectionService->detect(
            'business_partners', 'bp_code', $data['bp_code'], 'partner_name', $data['partner_name'], null, 'partner_id'
        );
        if ($detection['exact_duplicate']) {
            return response()->json(['message' => 'A business partner with this BP code already exists.'], 409);
        }

        $partner = BusinessPartner::create($data);

        $this->auditLogService->log('created', 'BusinessPartner', $partner->partner_id, $decidedBy, [], $partner->toArray(), null);

        return response()->json(['data' => $partner], 201);
    }

    /**
     * GET /internal/vendors/lookup?tin=...&name=...
     * Cross-validates OCR-extracted vendor name & TIN from SERMS against
     * CMS supplier master data.
     */
    public function lookup(Request $request)
    {
        $tin = trim((string) $request->query('tin', ''));
        $name = trim((string) $request->query('name', ''));

        if ($tin === '' && $name === '') {
            return response()->json(['message' => 'Provide either tin or name query parameter.'], 422);
        }

        $supplier = null;
        $matchType = null;

        if ($tin !== '') {
            $normalizedTin = preg_replace('/[^0-9]/', '', $tin) ?? '';
            $supplier = Supplier::where('tin_number', $tin)->first();

            if (!$supplier && $normalizedTin !== '') {
                $supplier = Supplier::query()
                    ->whereNotNull('tin_number')
                    ->get()
                    ->first(static fn (Supplier $s): bool => (preg_replace('/[^0-9]/', '', (string) $s->tin_number) ?? '') === $normalizedTin);
            }

            if ($supplier) {
                $matchType = 'tin';
            }
        }

        if (!$supplier && $name !== '') {
            $needle = mb_strtolower($name);
            $supplier = Supplier::query()
                ->whereRaw('LOWER(supplier_name) = ?', [$needle])
                ->first()
                ?? Supplier::query()->where('supplier_name', 'like', '%' . $name . '%')->first();

            if ($supplier) {
                $matchType = 'name';
            }
        }

        if (!$supplier) {
            return response()->json([
                'data' => [
                    'matched'                  => false,
                    'match_type'               => null,
                    'requires_audit'           => true,
                    'is_accredited'            => false,
                    'is_flagged'               => false,
                    'status'                   => null,
                    'default_expense_category' => null,
                    'supplier_id'              => null,
                    'supplier_name'            => null,
                    'tin_number'               => null,
                ],
            ]);
        }

        $requiresAudit = (bool) $supplier->is_flagged || $supplier->status !== 'Active' || !(bool) $supplier->is_accredited;

        return response()->json([
            'data' => [
                'matched'                  => true,
                'match_type'               => $matchType,
                'requires_audit'           => $requiresAudit,
                'is_accredited'            => (bool) $supplier->is_accredited,
                'is_flagged'               => (bool) $supplier->is_flagged,
                'status'                   => $supplier->status,
                'default_expense_category' => $supplier->default_expense_category,
                'supplier_id'              => $supplier->supplier_id,
                'supplier_name'            => $supplier->supplier_name,
                'tin_number'               => $supplier->tin_number,
            ],
        ]);
    }

    /**
     * POST /internal/vendors/suggest
     * Allows SERMS to submit a newly verified merchant as a Suggested Vendor
     * for CMS supplier onboarding.
     */
    public function suggestFromSerms(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vendor_name'              => 'required|string|max:255',
            'tin_number'               => 'nullable|string|max:100',
            'industry'                 => 'nullable|string|max:100',
            'contact_person'           => 'nullable|string|max:255',
            'contact_number'           => 'nullable|string|max:100',
            'email'                    => 'nullable|email|max:255',
            'address'                  => 'nullable|string',
            'region'                   => 'nullable|string|max:100',
            'default_expense_category' => 'nullable|string|max:100',
            'source_receipt_id'        => 'nullable|string|max:100',
            'submitted_by'             => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (!empty($data['tin_number'])) {
            $existingSupplier = Supplier::where('tin_number', $data['tin_number'])->first();
            if ($existingSupplier) {
                return response()->json([
                    'message'     => 'Vendor already exists in CMS supplier master list.',
                    'supplier_id' => $existingSupplier->supplier_id,
                ], 409);
            }

            $existingSuggestion = SuggestedVendor::where('tin_number', $data['tin_number'])
                ->where('status', 'Pending')
                ->first();
            if ($existingSuggestion) {
                return response()->json(['data' => $existingSuggestion], 200);
            }
        }

        $suggestion = SuggestedVendor::create([
            ...$data,
            'source_system' => 'SERMS',
            'status'        => 'Pending',
        ]);

        return response()->json(['data' => $suggestion], 201);
    }
}
