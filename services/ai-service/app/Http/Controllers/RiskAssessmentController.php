<?php

namespace App\Http\Controllers;

use App\Jobs\RunRiskAssessmentPipeline;
use App\Models\RiskAssessmentResult;
use App\Services\ContractOwnershipService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * US-026: AI Risk Assessment Summary — trigger, read, and PDF-export
 * endpoints on top of the RAG pipeline (RiskAssessmentPipeline). Role
 * matrix: Manager (decision), Sales (view, own contracts only).
 */
class RiskAssessmentController extends Controller
{
    public function __construct(protected ContractOwnershipService $ownership)
    {
    }

    /**
     * Resolve an alphanumeric contract code (or numeric ID) to its numeric ID and metadata.
     */
    protected function resolveNumericContractId(string $contractId): ?array
    {
        if (is_numeric($contractId)) {
            $num = (int) $contractId;
            $info = $this->ownership->resolveContract($num);
            return [
                'contract_id'   => $num,
                'contract_code' => $info['contract_code'] ?? null,
                'created_by'    => $info['created_by'] ?? null,
            ];
        }

        $info = $this->ownership->resolveContract($contractId);
        if (!$info || empty($info['contract_id'])) {
            return null;
        }

        return [
            'contract_id'   => (int) $info['contract_id'],
            'contract_code' => $info['contract_code'] ?? $contractId,
            'created_by'    => $info['created_by'] ?? null,
        ];
    }

    /**
     * POST /contracts/{contractId}/risk-assessment/scan
     */
    public function scan(Request $request, string $contractId)
    {
        $resolved = $this->resolveNumericContractId($contractId);
        if (!$resolved) {
            return response()->json(['message' => 'Contract not found.'], 404);
        }

        $numericId = $resolved['contract_id'];

        if ($denied = $this->denyIfNotAuthorized($request, $numericId, $resolved['created_by'])) {
            return $denied;
        }

        RunRiskAssessmentPipeline::dispatch($numericId);

        return response()->json([
            'message' => 'AI Risk Assessment scan queued.',
        ], 202);
    }

    /**
     * GET /contracts/{contractId}/risk-assessment/summary
     */
    public function summary(Request $request, string $contractId)
    {
        $resolved = $this->resolveNumericContractId($contractId);
        if (!$resolved) {
            return response()->json(['message' => 'No risk assessment has been run for this contract yet.'], 404);
        }

        $numericId = $resolved['contract_id'];

        if ($denied = $this->denyIfNotAuthorized($request, $numericId, $resolved['created_by'])) {
            return $denied;
        }

        $result = $this->latestResult($numericId);

        if (!$result) {
            return response()->json(['message' => 'No risk assessment has been run for this contract yet.'], 404);
        }

        $formatted = $this->formatResult($result);
        if (!empty($resolved['contract_code'])) {
            $formatted['contract_code'] = $resolved['contract_code'];
        }

        return response()->json(['data' => $formatted]);
    }

    /**
     * GET /contracts/{contractId}/risk-assessment/summary/pdf
     */
    public function summaryPdf(Request $request, string $contractId)
    {
        $resolved = $this->resolveNumericContractId($contractId);
        if (!$resolved) {
            return response()->json(['message' => 'No risk assessment has been run for this contract yet.'], 404);
        }

        $numericId = $resolved['contract_id'];

        if ($denied = $this->denyIfNotAuthorized($request, $numericId, $resolved['created_by'])) {
            return $denied;
        }

        $result = $this->latestResult($numericId);

        if (!$result) {
            return response()->json(['message' => 'No risk assessment has been run for this contract yet.'], 404);
        }

        $findings = $result->findingRows()->with('playbookClause')->orderByDesc('severity')->get();

        $html = view('pdf.risk_assessment_summary', [
            'result'   => $result,
            'findings' => $findings,
        ])->render();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $fileIdentifier = $resolved['contract_code'] ?? $numericId;

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"risk-assessment-contract-{$fileIdentifier}.pdf\"",
        ]);
    }

    /**
     * GET /contracts/risk-assessment/bulk-levels?ids=1,2,3
     */
    public function bulkLevels(Request $request)
    {
        $rawIds = array_filter(explode(',', $request->query('ids', '')));
        if (empty($rawIds)) {
            return response()->json(['data' => []]);
        }

        // Batch resolve IDs (handling both numeric IDs and alphanumeric contract codes)
        $resolvedList = $this->ownership->resolveBatch($rawIds);

        $numericToCode = [];
        $numericToOwner = [];
        $allNumericIds = [];

        foreach ($resolvedList as $item) {
            if (isset($item['contract_id'])) {
                $num = (int) $item['contract_id'];
                $allNumericIds[] = $num;
                if (!empty($item['contract_code'])) {
                    $numericToCode[$num] = $item['contract_code'];
                }
                $numericToOwner[$num] = $item['created_by'] ?? null;
            }
        }

        foreach ($rawIds as $id) {
            if (is_numeric($id)) {
                $allNumericIds[] = (int) $id;
            }
        }
        $allNumericIds = array_values(array_unique($allNumericIds));

        if (empty($allNumericIds)) {
            return response()->json(['data' => []]);
        }

        // Query only with clean integers so Postgres bigint column does not error
        $results = RiskAssessmentResult::whereIn('contract_id', $allNumericIds)
            ->orderByDesc('id')
            ->get()
            ->unique('contract_id');

        $data = [];
        foreach ($results as $result) {
            $numId = (int) $result->contract_id;
            $owner = $numericToOwner[$numId] ?? null;
            if ($denied = $this->denyIfNotAuthorized($request, $numId, $owner)) {
                continue; // Skip contracts the user is not allowed to view
            }
            $payload = [
                'risk_level'     => $result->risk_level,
                'findings_count' => $result->findingRows()->count(),
                'status'         => $result->status,
            ];
            // Key by numeric ID
            $data[(string) $numId] = $payload;
            // Also key by contract_code if known so frontend matches by contract code
            if (isset($numericToCode[$numId])) {
                $data[$numericToCode[$numId]] = $payload;
            }
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Manager/Admin can access any contract's assessment. Sales/Employee may
     * only access assessments for contracts they created. Returns a 403
     * JsonResponse if denied, or null if allowed.
     */
    protected function denyIfNotAuthorized(Request $request, int $contractId, ?int $createdBy = null): ?\Illuminate\Http\JsonResponse
    {
        $role = $request->get('auth_role');

        if (in_array($role, ['Manager', 'Admin'], true)) {
            return null;
        }

        $userId = (int) $request->get('auth_id');
        if ($createdBy === null) {
            $createdBy = $this->ownership->getCreatedBy($contractId);
        }

        if ($createdBy !== null && $createdBy === $userId) {
            return null;
        }

        return response()->json(['message' => 'Forbidden. You may only view AI Risk Assessments for your own contracts.'], 403);
    }

    protected function latestResult(int $contractId): ?RiskAssessmentResult
    {
        return RiskAssessmentResult::where('contract_id', $contractId)
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->first();
    }

    protected function formatResult(RiskAssessmentResult $result): array
    {
        $findings = $result->findingRows()->with('playbookClause')->orderByDesc('severity')->get();

        return [
            'contract_id' => $result->contract_id,
            'risk_score'  => $result->risk_score,
            'risk_level'  => $result->risk_level,
            'status'      => $result->status,
            'scanned_at'  => $result->scanned_at?->toISOString(),
            'findings'    => $findings->map(fn ($f) => [
                'id'                       => $f->id,
                'clause_reference'         => $f->clause_reference,
                'severity'                 => $f->severity,
                'playbook_clause_code'     => $f->playbookClause?->clause_code,
                'playbook_clause_title'    => $f->playbookClause?->title,
                'deviation_reason'         => $f->deviation_reason,
                'recommended_remediation'  => $f->recommended_remediation,
            ])->values(),
        ];
    }
}
