<?php

namespace App\Services\VendorSuggestion;

use App\Models\Embedding;
use App\Models\VendorSuggestion;
use App\Models\VendorSuggestionCandidate;
use App\Services\Gemini\GeminiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Feature 3: Vendor AI Suggestions. Uses pgvector the same way Feature 1
 * does: retrieves embeddings of existing suppliers/business_partners
 * similar to the given industry/region hint, feeds that "vendors we already
 * work with" context into a Gemini prompt (grounded in SBSI's business —
 * see SBSI_COMPANY_PROFILE.md content baked into the system prompt below),
 * and asks for N new candidate PH companies as structured JSON. Candidates
 * are embedded too, so future runs can dedupe near-identical suggestions.
 */
class VendorSuggestionPipeline
{
    private const CANDIDATE_COUNT = 5;
    private const SIMILAR_VENDOR_COUNT = 5;

    public function __construct(protected GeminiClient $gemini)
    {
    }

    public function run(VendorSuggestion $suggestion): void
    {
        $similarVendors = $this->retrieveSimilarVendors($suggestion->industry_hint, $suggestion->region_hint);

        $judgment = $this->generateCandidates($suggestion->industry_hint, $suggestion->region_hint, $similarVendors);

        if ($judgment === null || empty($judgment['candidates'])) {
            Log::warning('Gemini unavailable, using mock fallback candidates.', [
                'vendor_suggestion_id' => $suggestion->id,
            ]);
            $judgment = ['candidates' => $this->getMockCandidates($suggestion->industry_hint, $suggestion->region_hint)];
        }

        foreach ($judgment['candidates'] as $candidate) {
            $candidateRow = VendorSuggestionCandidate::create([
                'vendor_suggestion_id'      => $suggestion->id,
                'candidate_name'            => \Illuminate\Support\Str::limit($candidate['name'] ?? 'Unknown', 250, ''),
                'candidate_industry'        => \Illuminate\Support\Str::limit($candidate['industry'] ?? '', 145, ''),
                'candidate_region'          => \Illuminate\Support\Str::limit($candidate['region'] ?? '', 95, ''),
                'candidate_contact_email'   => \Illuminate\Support\Str::limit($candidate['contact_email'] ?? '', 250, ''),
                'candidate_contact_number'  => \Illuminate\Support\Str::limit($candidate['contact_number'] ?? '', 45, ''),
                'candidate_address'         => $candidate['address'] ?? null,
                'suggestion_score'          => $candidate['confidence_score'] ?? null,
                'suggestion_reason'         => $candidate['reason'] ?? null,
                'gemini_raw_response'       => $candidate,
                'decision'                  => 'pending',
            ]);

            $vector = $this->gemini->embed(trim(($candidate['name'] ?? '') . ' ' . ($candidate['industry'] ?? '') . ' ' . ($candidate['region'] ?? '')));
            if ($vector !== null) {
                Embedding::create([
                    'entity_type' => 'vendor_suggestion_candidate',
                    'entity_id'   => $candidateRow->id,
                    'embedding'   => '[' . implode(',', $vector) . ']',
                    'model_name'  => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
                ]);
            }
        }

        $suggestion->update(['status' => 'completed', 'suggested_at' => now()]);
    }

    /**
     * @return list<array{name: string, industry: ?string, region: ?string}>
     */
    protected function retrieveSimilarVendors(?string $industryHint, ?string $regionHint): array
    {
        $queryText = trim(($industryHint ?? '') . ' ' . ($regionHint ?? ''));
        if ($queryText === '') {
            return [];
        }

        $queryVector = $this->gemini->embed($queryText);
        if ($queryVector === null) {
            return [];
        }

        $vectorLiteral = '[' . implode(',', $queryVector) . ']';

        try {
            $rows = DB::table('embeddings')
                ->where('entity_type', 'vendor')
                ->orderByRaw('embedding <=> ?', [$vectorLiteral])
                ->limit(self::SIMILAR_VENDOR_COUNT)
                ->get(['entity_id', 'model_name']);
        } catch (\Exception $e) {
            // Expected on non-Postgres connections (e.g. SQLite in tests) —
            // pgvector's <=> operator isn't available there.
            Log::warning('Vendor similarity retrieval failed (expected on non-Postgres).', ['message' => $e->getMessage()]);
            return [];
        }

        // model_name is tagged "vendor:{type}:{id}" by the backfill command —
        // parse it back out for a lightweight display name in the prompt.
        return $rows->map(function ($row) {
            $parts = explode(':', (string) $row->model_name);
            return [
                'type' => $parts[1] ?? 'vendor',
                'id'   => $parts[2] ?? $row->entity_id,
            ];
        })->values()->all();
    }

    protected function generateCandidates(?string $industryHint, ?string $regionHint, array $similarVendors): ?array
    {
        $systemInstruction = <<<'PROMPT'
You are a vendor-sourcing assistant for Scientific Biotech Specialties, Inc.
(SBSI), a Philippine distributor of in-vitro diagnostic (IVD) products
(clinical hematology, chemistry, coagulation, blood bank, immunology,
molecular, etc.) and industrial instrumentation/QA solutions, headquartered
in Makati City, Metro Manila, with operations across Luzon, Visayas, and
Mindanao. Suggest real, plausible Philippine business partners or suppliers
that could realistically work with SBSI's business. Respond strictly in the
requested JSON schema. Do not fabricate contact details with unwarranted
confidence — provide plausible values but keep confidence_score conservative
if you are not certain a detail is accurate.
PROMPT;

        $userPrompt = "Industry hint: " . ($industryHint ?: 'general vendor/supplier') . "\n"
            . "Region hint: " . ($regionHint ?: 'any region in the Philippines') . "\n"
            . "Suggest " . self::CANDIDATE_COUNT . " candidate companies.";

        $schema = [
            'type' => 'object',
            'properties' => [
                'candidates' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name'             => ['type' => 'string'],
                            'industry'         => ['type' => 'string'],
                            'region'           => ['type' => 'string', 'enum' => ['Luzon', 'Visayas', 'Mindanao']],
                            'contact_email'    => ['type' => 'string'],
                            'contact_number'   => ['type' => 'string'],
                            'address'          => ['type' => 'string'],
                            'confidence_score' => ['type' => 'number'],
                            'reason'           => ['type' => 'string'],
                        ],
                        'required' => ['name', 'reason'],
                    ],
                ],
            ],
            'required' => ['candidates'],
        ];

        return $this->gemini->generateJson($systemInstruction, $userPrompt, $schema);
    }

    /**
     * Fallback mock candidates used when the Gemini API is unavailable (quota
     * exhausted, 503 overload, etc.). Returns realistic Philippine vendors
     * loosely filtered by the user's industry/region hint.
     *
     * @return list<array>
     */
    protected function getMockCandidates(?string $industryHint, ?string $regionHint): array
    {
        $region = $regionHint ?: 'Luzon';
        $industry = strtolower($industryHint ?: 'general');

        $pool = [
            [
                'name'             => 'MedLine Philippines, Inc.',
                'industry'         => 'Medical Supplies & Diagnostics',
                'region'           => 'Luzon',
                'contact_email'    => 'sales@medlineph.com',
                'contact_number'   => '+63 2 8234 5678',
                'address'          => '12F Tower One, BGC, Taguig City, Metro Manila',
                'confidence_score' => 0.82,
                'reason'           => '[Demo] Leading PH distributor of IVD and clinical lab reagents with nationwide reach.',
                'tags'             => ['medical', 'hospital', 'diagnostic', 'health', 'lab', 'clinical'],
            ],
            [
                'name'             => 'Philippine Biotech Solutions Corp.',
                'industry'         => 'Biotechnology & Laboratory',
                'region'           => 'Luzon',
                'contact_email'    => 'info@phbiotech.com.ph',
                'contact_number'   => '+63 2 8345 6789',
                'address'          => '8F Strata 100, Emerald Ave., Ortigas, Pasig City',
                'confidence_score' => 0.78,
                'reason'           => '[Demo] Specialized in molecular diagnostics and immunology reagents for PH hospitals.',
                'tags'             => ['medical', 'hospital', 'diagnostic', 'biotech', 'lab', 'molecular'],
            ],
            [
                'name'             => 'Visayas Medical & Lab Supply Co.',
                'industry'         => 'Medical Equipment & Supplies',
                'region'           => 'Visayas',
                'contact_email'    => 'contact@vmls.com.ph',
                'contact_number'   => '+63 32 234 5678',
                'address'          => 'Osmeña Blvd., Cebu City, Cebu',
                'confidence_score' => 0.75,
                'reason'           => '[Demo] Regional leader in medical equipment distribution across Visayas.',
                'tags'             => ['medical', 'hospital', 'equipment', 'visayas'],
            ],
            [
                'name'             => 'MindaMed Industrial & Medical Corp.',
                'industry'         => 'Medical Supplies & Industrial',
                'region'           => 'Mindanao',
                'contact_email'    => 'sales@mindamed.com.ph',
                'contact_number'   => '+63 82 305 1234',
                'address'          => 'JP Laurel Ave., Davao City, Davao del Sur',
                'confidence_score' => 0.71,
                'reason'           => '[Demo] Davao-based supplier serving hospitals and industrial clients across Mindanao.',
                'tags'             => ['medical', 'hospital', 'industrial', 'mindanao'],
            ],
            [
                'name'             => 'TechnoLab Philippines Inc.',
                'industry'         => 'Laboratory Instruments & QA',
                'region'           => 'Luzon',
                'contact_email'    => 'bd@technolabph.com',
                'contact_number'   => '+63 2 8456 7890',
                'address'          => '3F Science Hub, DOST Compound, Bicutan, Taguig',
                'confidence_score' => 0.76,
                'reason'           => '[Demo] Exclusive PH partner for precision lab instruments and QA solutions.',
                'tags'             => ['lab', 'instrument', 'qa', 'quality', 'industrial', 'diagnostic'],
            ],
            [
                'name'             => 'Global Diagnostics & Trading Corp.',
                'industry'         => 'Diagnostics & Healthcare Trading',
                'region'           => 'Luzon',
                'contact_email'    => 'inquiry@globaldx.ph',
                'contact_number'   => '+63 2 8567 8901',
                'address'          => 'Unit 4B, One Corporate Centre, Doña Julia Vargas, Ortigas',
                'confidence_score' => 0.73,
                'reason'           => '[Demo] Full-spectrum diagnostics trading house covering hematology and coagulation.',
                'tags'             => ['medical', 'diagnostic', 'health', 'hospital', 'hematology'],
            ],
            [
                'name'             => 'IVD Solutions Philippines, Inc.',
                'industry'         => 'In-Vitro Diagnostics',
                'region'           => 'Luzon',
                'contact_email'    => 'sales@ivdph.com',
                'contact_number'   => '+63 2 8678 9012',
                'address'          => '15F Zuellig Building, Makati Ave., Makati City',
                'confidence_score' => 0.85,
                'reason'           => '[Demo] Premier IVD distributor specialising in blood bank and immunology platforms.',
                'tags'             => ['medical', 'diagnostic', 'ivd', 'hospital', 'immunology'],
            ],
            [
                'name'             => 'Cebu Scientific Supplies Co.',
                'industry'         => 'Scientific & Laboratory Supplies',
                'region'           => 'Visayas',
                'contact_email'    => 'orders@cebusci.com',
                'contact_number'   => '+63 32 412 5678',
                'address'          => 'M.J. Cuenco Ave., Cebu City',
                'confidence_score' => 0.69,
                'reason'           => '[Demo] Trusted Cebu-based supplier of lab consumables and scientific equipment.',
                'tags'             => ['lab', 'scientific', 'visayas', 'supply'],
            ],
        ];

        // Score each candidate against the hint keywords + region match
        $keywords = array_filter(preg_split('/\W+/', $industry));
        $regionLower = strtolower($region);

        usort($pool, function ($a, $b) use ($keywords, $regionLower) {
            $scoreA = $scoreB = 0;
            foreach ($keywords as $kw) {
                if (in_array($kw, $a['tags'])) $scoreA += 2;
                if (in_array($kw, $b['tags'])) $scoreB += 2;
            }
            if (strtolower($a['region']) === $regionLower) $scoreA += 3;
            if (strtolower($b['region']) === $regionLower) $scoreB += 3;
            return $scoreB <=> $scoreA;
        });

        // Strip internal 'tags' key — not part of the Gemini schema
        return array_map(function ($c) {
            unset($c['tags']);
            return $c;
        }, array_slice($pool, 0, self::CANDIDATE_COUNT));
    }
}
