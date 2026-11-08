<?php

namespace Tests\Feature;

use App\Models\AggregatedMetric;
use App\Services\DiagnosticAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature 4: covers that diagnostic insights are computed algorithmically
 * from already-aggregated metrics (no LLM call needed to produce the
 * finding_summary), and that the optional Gemini narrative degrades
 * gracefully (null, not an exception) when the API call fails — never
 * blocking the deterministic diagnostic result.
 */
class DiagnosticAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('GEMINI_API_KEY=test-key');
        $_ENV['GEMINI_API_KEY'] = 'test-key';
    }

    public function test_produces_no_insight_when_no_metrics_exist_yet(): void
    {
        Http::fake();

        $service = app(DiagnosticAnalysisService::class);
        $insights = $service->runAll();

        $this->assertCount(0, $insights);
    }

    public function test_computes_risk_flag_rate_insight_from_aggregated_metrics(): void
    {
        AggregatedMetric::create([
            'metric_type'    => 'avg_risk_score',
            'source_service' => 'ai-service',
            'metric_value'   => 60,
            'metric_date'    => now()->toDateString(),
        ]);

        Http::fake([
            'http://contract-management:8000/api/internal/metrics/contracts/by-segment' => Http::response(['data' => []], 200),
            '*generateContent*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Risk rose slightly this period.']]]]],
            ], 200),
        ]);

        $service = app(DiagnosticAnalysisService::class);
        $insights = $service->runAll();

        $this->assertGreaterThan(0, count($insights));
        $this->assertDatabaseHas('diagnostic_insights', [
            'metric_type' => 'risk_flag_rate',
        ]);

        $insight = collect($insights)->firstWhere('metric_type', 'risk_flag_rate');
        $this->assertNotNull($insight);
        $this->assertEquals(60.0, $insight->finding_summary['current_period_avg']);
        $this->assertSame('Risk rose slightly this period.', $insight->ai_narrative);
    }

    public function test_finding_summary_is_computed_even_if_gemini_narrative_fails(): void
    {
        AggregatedMetric::create([
            'metric_type'    => 'avg_risk_score',
            'source_service' => 'ai-service',
            'metric_value'   => 60,
            'metric_date'    => now()->toDateString(),
        ]);

        Http::fake([
            'http://contract-management:8000/api/internal/metrics/contracts/by-segment' => Http::response(['data' => []], 200),
            '*generateContent*' => Http::response(['error' => 'server error'], 500),
        ]);

        $service = app(DiagnosticAnalysisService::class);
        $insights = $service->runAll();

        $insight = collect($insights)->firstWhere('metric_type', 'risk_flag_rate');
        $this->assertNotNull($insight);
        $this->assertNull($insight->ai_narrative);
        $this->assertEquals(60.0, $insight->finding_summary['current_period_avg']);
    }
}
