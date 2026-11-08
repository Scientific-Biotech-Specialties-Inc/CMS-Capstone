<?php

namespace App\Http\Controllers;

use App\Jobs\RunVendorSuggestionPipeline;
use App\Models\VendorSuggestion;
use App\Models\VendorSuggestionCandidate;
use App\Services\VendorManagementClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Feature 3: Vendor AI Suggestions. Admin-only (enforced by the frontend's
 * permission gate + this controller's own role check, matching the
 * confirmed plan's "Admin-only" placement).
 */
class VendorSuggestionController extends Controller
{
    public function __construct(protected VendorManagementClient $vendorClient)
    {
    }

    /**
     * POST /vendor-suggestions — starts a new suggestion batch.
     */
    public function store(Request $request)
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'industry_hint' => 'nullable|string|max:150',
            'region_hint'   => 'nullable|string|in:Luzon,Visayas,Mindanao',
            'contract_id'   => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $suggestion = VendorSuggestion::create([
            'contract_id'   => $data['contract_id'] ?? null,
            'requested_by'  => $request->get('auth_id'),
            'industry_hint' => $data['industry_hint'] ?? null,
            'region_hint'   => $data['region_hint'] ?? null,
            'status'        => 'pending',
        ]);

        RunVendorSuggestionPipeline::dispatch($suggestion->id);

        return response()->json([
            'message' => 'Vendor AI suggestion queued.',
            'data'    => ['id' => $suggestion->id, 'status' => $suggestion->status],
        ], 202);
    }

    /**
     * GET /vendor-suggestions/{id} — poll status + candidates.
     */
    public function show(Request $request, int $id)
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $suggestion = VendorSuggestion::with('candidates')->find($id);
        if (!$suggestion) {
            return response()->json(['message' => 'Suggestion batch not found.'], 404);
        }

        return response()->json(['data' => $this->formatSuggestion($suggestion)]);
    }

    /**
     * GET /vendor-suggestions — latest batch (for the list page's default load).
     */
    public function latest(Request $request)
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $suggestion = VendorSuggestion::with('candidates')
            ->where('requested_by', $request->get('auth_id'))
            ->orderByDesc('id')
            ->first();

        if (!$suggestion) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->formatSuggestion($suggestion)]);
    }

    /**
     * PATCH /vendor-suggestion-candidates/{id} — record accept/dismiss.
     * On accept, creates the real supplier/partner via vendor-management
     * using the (user-reviewed/edited) fields provided in the request —
     * never the raw Gemini output directly, matching the plan's caveat that
     * AI-suggested contact details are a starting point, not verified data.
     */
    public function decide(Request $request, int $id)
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $candidate = VendorSuggestionCandidate::find($id);
        if (!$candidate) {
            return response()->json(['message' => 'Candidate not found.'], 404);
        }

        if ($candidate->decision !== 'pending') {
            return response()->json(['message' => 'A decision has already been recorded for this candidate.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'decision'              => 'required|string|in:accepted,dismissed',
            'vendor_type'           => 'required_if:decision,accepted|string|in:supplier,partner',
            'fields'                => 'required_if:decision,accepted|array',
            'fields.name'           => 'required_if:decision,accepted|string|max:255',
            'fields.industry'       => 'nullable|string|max:150',
            'fields.region'         => 'nullable|string|in:Luzon,Visayas,Mindanao',
            'fields.email'          => 'nullable|email|max:255',
            'fields.phone'          => 'nullable|string|max:50',
            'fields.address'        => 'nullable|string',
            'fields.contact_person' => 'nullable|string|max:255',
            'fields.tin_number'     => 'nullable|string|max:100',
            'fields.bp_code'        => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $decidedBy = $request->get('auth_id');

        if ($data['decision'] === 'dismissed') {
            $candidate->update(['decision' => 'dismissed', 'decided_by' => $decidedBy, 'decided_at' => now()]);
            return response()->json(['message' => 'Candidate dismissed.', 'data' => $candidate->fresh()]);
        }

        $result = $this->vendorClient->createVendor($data['vendor_type'], $data['fields'], $decidedBy);

        if (!$result['ok']) {
            return response()->json(['message' => $result['message'] ?? 'Failed to create vendor.'], 422);
        }

        $candidate->update([
            'decision'   => 'accepted',
            'decided_by' => $decidedBy,
            'decided_at' => now(),
        ]);

        return response()->json([
            'message' => 'Candidate accepted and vendor created.',
            'data'    => $candidate->fresh(),
            'vendor_id' => $result['id'] ?? null,
        ]);
    }

    private function isAuthorized(Request $request): bool
    {
        $role = $request->get('auth_role');
        return in_array($role, ['Admin', 'Super Admin'], true);
    }

    private function formatSuggestion(VendorSuggestion $suggestion): array
    {
        return [
            'id'            => $suggestion->id,
            'status'        => $suggestion->status,
            'industry_hint' => $suggestion->industry_hint,
            'region_hint'   => $suggestion->region_hint,
            'candidates'    => $suggestion->candidates->map(fn ($c) => [
                'id'               => $c->id,
                'name'             => $c->candidate_name,
                'industry'         => $c->candidate_industry,
                'region'           => $c->candidate_region,
                'contact_email'    => $c->candidate_contact_email,
                'contact_number'   => $c->candidate_contact_number,
                'address'          => $c->candidate_address,
                'suggestion_score' => $c->suggestion_score,
                'suggestion_reason' => $c->suggestion_reason,
                'decision'         => $c->decision,
            ])->values(),
        ];
    }
}
