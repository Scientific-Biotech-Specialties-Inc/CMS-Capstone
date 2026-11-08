<?php

namespace App\Services\RiskAssessment;

use App\Models\Embedding;
use App\Models\PlaybookClause;
use App\Models\RiskAssessmentFinding;
use App\Models\RiskAssessmentResult;
use App\Services\ContractDocumentClient;
use App\Services\Gemini\GeminiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * US-026: AI Risk Assessment — optimised single-call RAG pipeline.
 *
 * Architecture (2 API calls per scan, down from 30+):
 *   1. Extract full contract text from all attached documents
 *   2. Embed the contract text once  → 1 embedding call
 *   3. pgvector cosine-similarity retrieves the TOP_K most relevant playbook
 *      clauses   → 0 API calls (DB query only)
 *   4. Ask Gemini ONCE with the full contract + all retrieved clauses →
 *      structured-JSON array of findings   → 1 generate call
 *   5. Map clause_code back to DB IDs and persist findings + result
 *
 * The previous per-chunk approach made N_chunks × (1 embed + TOP_K judges)
 * calls (≈30 for a typical contract), exhausting the free-tier daily quota
 * on a single scan.  This implementation keeps pgvector for semantic retrieval
 * while collapsing all judgment calls into one.
 */
class RiskAssessmentPipeline
{
    /** How many playbook clauses to retrieve per scan via pgvector */
    private const TOP_K = 10;

    public function __construct(
        protected GeminiClient $gemini,
        protected DocumentTextExtractor $extractor,
        protected ContractDocumentClient $documentClient,
    ) {
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Ensures every active playbook clause has an up-to-date embedding.
     * Idempotent — skips clauses that already have an embedding row.
     */
    public function indexPlaybook(): void
    {
        $clauses = PlaybookClause::where('is_active', true)->get();

        foreach ($clauses as $clause) {
            $exists = Embedding::where('entity_type', 'playbook_clause')
                ->where('entity_id', $clause->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $vector = $this->gemini->embed("{$clause->title}\n\n{$clause->standard_text}");
            if ($vector === null) {
                Log::warning('Failed to embed playbook clause, skipping.', ['clause_id' => $clause->id]);
                continue;
            }

            Embedding::create([
                'entity_type' => 'playbook_clause',
                'entity_id'   => $clause->id,
                'embedding'   => $this->formatVector($vector),
                'model_name'  => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
            ]);
        }
    }

    /**
     * Runs the full pipeline for a contract.
     *
     * Returns the created/updated RiskAssessmentResult, or a failed-status
     * result row if something goes wrong (so the frontend always gets a real
     * DB row to query, never a silent 404).
     */
    public function run(int $contractId): ?RiskAssessmentResult
    {
        // Step 0: index any un-embedded playbook clauses (idempotent)
        $this->indexPlaybook();

        // Step 1: fetch documents and extract full text
        $documents = $this->documentClient->listDocuments($contractId);
        if (empty($documents)) {
            return $this->markFailed($contractId, null, 'No documents found for this contract.');
        }

        $fullText       = '';
        $usedDocumentId = null;

        foreach ($documents as $doc) {
            $bytes = $this->documentClient->fetchFileBytes($doc['document_id']);
            if ($bytes === null) {
                continue;
            }

            $text = $this->extractor->extract($bytes, $doc['file_type']);
            if ($text === null) {
                continue;
            }

            $usedDocumentId ??= $doc['document_id'];
            $fullText       .= ($fullText ? "\n\n" : '') . $text;
        }

        if ($fullText === '') {
            return $this->markFailed(
                $contractId,
                $usedDocumentId,
                'No extractable text found (documents may be scanned/image-only with no text layer).'
            );
        }

        Log::info('Risk assessment: extracted contract text.', [
            'contract_id' => $contractId,
            'char_count'  => strlen($fullText),
        ]);

        // Step 2: embed the contract text (1 API call) — truncate to first 8 000
        // chars so the embedding stays within token limits while still capturing
        // the contract's core structure and clauses.
        $contractVector = $this->gemini->embed(mb_substr($fullText, 0, 8000));
        if ($contractVector === null) {
            return $this->markFailed(
                $contractId,
                $usedDocumentId,
                'Failed to generate contract embedding — Gemini API may be unavailable or quota exhausted.'
            );
        }

        // Step 3: retrieve most-relevant playbook clauses via pgvector (0 API calls)
        $clauses = $this->retrieveTopPlaybookClauses($contractVector, self::TOP_K);

        if (empty($clauses)) {
            // pgvector returned nothing — fall back to all active clauses so we
            // can still run the assessment (just without semantic ranking).
            $clauses = PlaybookClause::where('is_active', true)->get()->all();
            Log::info('pgvector retrieval returned no results, falling back to all active clauses.', [
                'contract_id' => $contractId,
            ]);
        }

        if (empty($clauses)) {
            return $this->markFailed(
                $contractId,
                $usedDocumentId,
                'No active playbook clauses found to assess against.'
            );
        }

        Log::info('Risk assessment: clauses retrieved for assessment.', [
            'contract_id'  => $contractId,
            'clause_count' => count($clauses),
        ]);

        // Step 4: single Gemini call to assess the full contract (1 API call)
        $findings = $this->assessFullContract($fullText, $clauses);

        if ($findings === null) {
            // Gemini call failed — mark as failed rather than silently returning
            // risk_level='low' with 0 findings (which was the old misleading behaviour).
            return $this->markFailed(
                $contractId,
                $usedDocumentId,
                'AI assessment failed — Gemini API unavailable or quota exhausted. Please retry later.'
            );
        }

        Log::info('Risk assessment: Gemini returned findings.', [
            'contract_id'   => $contractId,
            'finding_count' => count($findings),
        ]);

        // Step 5: persist result
        return $this->saveResult($contractId, $usedDocumentId, $findings);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Pipeline steps
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Retrieves the TOP_K most-similar playbook clauses to the contract
     * embedding using pgvector cosine distance.
     *
     * @return list<PlaybookClause>
     */
    protected function retrieveTopPlaybookClauses(array $contractVector, int $topK): array
    {
        $vectorLiteral = $this->formatVector($contractVector);

        try {
            $rows = DB::table('embeddings')
                ->where('entity_type', 'playbook_clause')
                ->selectRaw('entity_id, 1 - (embedding <=> ?) as similarity', [$vectorLiteral])
                ->orderByRaw('embedding <=> ?', [$vectorLiteral])
                ->limit($topK)
                ->get();
        } catch (\Exception $e) {
            Log::warning('pgvector retrieval query failed.', ['message' => $e->getMessage()]);
            return [];
        }

        $clauseIds = $rows->pluck('entity_id')->all();

        return PlaybookClause::whereIn('id', $clauseIds)
            ->where('is_active', true)
            ->get()
            ->all();
    }

    /**
     * Sends the full contract text + all retrieved clauses to Gemini in ONE
     * call and returns the parsed findings array.
     *
     * Returns null when the Gemini call itself fails (quota/network error).
     * Returns an empty array [] when Gemini succeeds but finds zero deviations
     * (i.e. the contract is compliant) — callers must treat these differently.
     *
     * @param  list<PlaybookClause>  $clauses
     * @return list<array{clause_reference: string, severity: string, playbook_clause_id: ?int, retrieval_score: null, deviation_reason: string, recommended_remediation: string}>|null
     */
    protected function assessFullContract(string $fullText, array $clauses): ?array
    {
        $systemInstruction = <<<'PROMPT'
You are a contract-risk analyst reviewing a commercial contract against a set of
standard playbook clauses. For each clause provided, carefully assess whether the
contract text deviates from that clause's standard requirement.

Rules:
- Only include a finding when deviates = true (a real, material deviation exists).
- If the contract is silent on a clause topic, that is also a deviation — flag it.
- Do NOT flag stylistic or formatting differences.
- Be specific: quote or paraphrase the problematic contract language in deviation_reason.
- Respond strictly in the requested JSON schema.
PROMPT;

        // Build clause context block
        $clausesContext = collect($clauses)
            ->map(fn (PlaybookClause $c) =>
                "CLAUSE [{$c->clause_code}] — {$c->title}:\n{$c->standard_text}"
            )
            ->implode("\n\n---\n\n");

        // Truncate to ~20 000 chars to stay within context window safely
        $contractExcerpt = mb_substr($fullText, 0, 20000);

        $userPrompt = "PLAYBOOK CLAUSES TO ASSESS:\n\n{$clausesContext}\n\n"
            . "====\n\n"
            . "CONTRACT TEXT:\n\n{$contractExcerpt}";

        $schema = [
            'type'       => 'object',
            'properties' => [
                'findings' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'clause_code'             => ['type' => 'string'],
                            'severity'                => [
                                'type' => 'string',
                                'enum' => ['low', 'medium', 'high', 'critical'],
                            ],
                            'deviation_reason'        => ['type' => 'string'],
                            'recommended_remediation' => ['type' => 'string'],
                        ],
                        'required' => ['clause_code', 'severity', 'deviation_reason', 'recommended_remediation'],
                    ],
                ],
            ],
            'required' => ['findings'],
        ];

        $result = $this->gemini->generateJson($systemInstruction, $userPrompt, $schema);

        if ($result === null) {
            Log::error('assessFullContract: Gemini call returned null.', [
                'clause_count'        => count($clauses),
                'contract_char_count' => strlen($fullText),
            ]);
            return null; // signals a hard failure to the caller
        }

        $rawFindings   = $result['findings'] ?? [];
        $clausesByCode = collect($clauses)->keyBy('clause_code');
        $mapped        = [];

        foreach ($rawFindings as $f) {
            $clauseCode = $f['clause_code'] ?? '';
            $clause     = $clausesByCode->get($clauseCode);

            $mapped[] = [
                'clause_reference'        => $clauseCode,
                'severity'                => $f['severity'] ?? 'medium',
                'playbook_clause_id'      => $clause?->id,
                'retrieval_score'         => null, // not applicable in single-call model
                'deviation_reason'        => $f['deviation_reason'] ?? '',
                'recommended_remediation' => $f['recommended_remediation'] ?? '',
            ];
        }

        return $mapped;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Persistence helpers
    // ──────────────────────────────────────────────────────────────────────────

    protected function saveResult(int $contractId, ?string $documentId, array $findings): RiskAssessmentResult
    {
        $riskLevel = $this->aggregateRiskLevel($findings);
        $riskScore = $this->aggregateRiskScore($findings);

        $result = RiskAssessmentResult::create([
            'document_id' => $documentId,
            'contract_id' => $contractId,
            'risk_score'  => $riskScore,
            'risk_level'  => $riskLevel,
            'findings'    => ['count' => count($findings)],
            'status'      => 'completed',
            'scanned_at'  => now(),
        ]);

        foreach ($findings as $finding) {
            RiskAssessmentFinding::create([
                'risk_assessment_result_id' => $result->id,
                'clause_reference'          => $finding['clause_reference'],
                'severity'                  => $finding['severity'],
                'playbook_clause_id'        => $finding['playbook_clause_id'],
                'retrieval_score'           => $finding['retrieval_score'],
                'deviation_reason'          => $finding['deviation_reason'],
                'recommended_remediation'   => $finding['recommended_remediation'],
                'created_at'                => now(),
            ]);
        }

        return $result;
    }

    protected function markFailed(int $contractId, ?string $documentId, string $reason): RiskAssessmentResult
    {
        Log::warning("Risk assessment marked failed for contract {$contractId}.", ['reason' => $reason]);

        return RiskAssessmentResult::create([
            'document_id' => $documentId,
            'contract_id' => $contractId,
            'risk_score'  => null,
            'risk_level'  => null,
            'findings'    => ['count' => 0, 'reason' => $reason],
            'status'      => 'failed',
            'scanned_at'  => now(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Aggregation helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns the highest severity level across all findings.
     * Returns 'low' only when Gemini genuinely found zero deviations (compliant contract).
     * A null/failed result is handled upstream via markFailed() — this method is never
     * called when the Gemini call itself failed.
     */
    protected function aggregateRiskLevel(array $findings): string
    {
        if (empty($findings)) {
            return 'low'; // Gemini responded with an empty findings array = clean contract
        }

        $severities = array_column($findings, 'severity');
        foreach (['critical', 'high', 'medium', 'low'] as $level) {
            if (in_array($level, $severities, true)) {
                return $level;
            }
        }

        return 'low';
    }

    protected function aggregateRiskScore(array $findings): float
    {
        $weights = ['low' => 10, 'medium' => 40, 'high' => 70, 'critical' => 95];
        if (empty($findings)) {
            return 0.0;
        }

        $scores = array_map(fn ($f) => $weights[$f['severity']] ?? 40, $findings);
        return round(max($scores), 2);
    }

    protected function formatVector(array $vector): string
    {
        return '[' . implode(',', $vector) . ']';
    }
}
