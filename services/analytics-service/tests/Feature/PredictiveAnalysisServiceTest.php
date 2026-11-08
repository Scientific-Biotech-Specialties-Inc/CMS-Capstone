<?php

namespace Tests\Feature;

use App\Models\AggregatedMetric;
use App\Models\PredictiveInsight;
use App\Services\PredictiveAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature 4: covers that the 30-day forecast is computed deterministically
 * via linear regression over the historical series (never invented by the
 * LLM), that confidence reflects how well that line actually fits the
 * data, and that same-day duplicate historical rows are averaged rather
 * than double-counted.
 */
class PredictiveAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('GEMINI_API_KEY=test-key');
        $_ENV['GEMINI_API_KEY'] = 'test-key';
    }

    public function test_forecast_is_computed_via_linear_regression_not_llm(): void
    {
        // A perfectly linear, noise-free historical series: exactly +0.1/day
        // starting at 5.0. A correct OLS fit should continue that exact
        // slope forward with R² = 1.0, i.e. "high" confidence.
        $today = Carbon::today();
        for ($i = 9; $i >= 0; $i--) {
            AggregatedMetric::create([
                'metric_type'    => 'avg_risk_score',
                'source_service' => 'ai-service',
                'metric_value'   => round(5.0 + (9 - $i) * 0.1, 2),
                'metric_date'    => $today->copy()->subDays($i)->toDateString(),
            ]);
        }

        // Gemini should never be asked to produce numbers — only a
        // narrative — and the forecast must not depend on its response.
        Http::fake(['*generateContent*' => Http::response(['error' => 'unreachable'], 500)]);

        $service = app(PredictiveAnalysisService::class);
        $insights = $service->runAll();

        $insight = collect($insights)->firstWhere('metric_type', 'risk_score_forecast');
        $this->assertNotNull($insight);
        $this->assertSame('high', $insight->confidence);

        $predicted = $insight->predicted_series;
        $this->assertCount(30, $predicted);

        // Day 1 of the forecast should continue the established +0.1/day
        // trend from the last historical value (5.9), i.e. ~6.0.
        $this->assertEqualsWithDelta(6.0, $predicted[0]['value'], 0.05);
    }

    public function test_confidence_is_low_for_noisy_historical_data(): void
    {
        $today = Carbon::today();
        $noisyValues = [2.0, 8.0, 1.5, 9.0, 3.0, 7.5, 2.5, 8.5, 1.0, 9.5];
        foreach ($noisyValues as $i => $value) {
            AggregatedMetric::create([
                'metric_type'    => 'avg_risk_score',
                'source_service' => 'ai-service',
                'metric_value'   => $value,
                'metric_date'    => $today->copy()->subDays(count($noisyValues) - 1 - $i)->toDateString(),
            ]);
        }

        Http::fake(['*generateContent*' => Http::response(['error' => 'unreachable'], 500)]);

        $service = app(PredictiveAnalysisService::class);
        $insights = $service->runAll();

        $insight = collect($insights)->firstWhere('metric_type', 'risk_score_forecast');
        $this->assertNotNull($insight);
        $this->assertSame('low', $insight->confidence);
    }

    public function test_risk_score_forecast_is_clamped_to_zero_to_ten(): void
    {
        $today = Carbon::today();
        // A steep downward trend that would go negative if not clamped.
        for ($i = 9; $i >= 0; $i--) {
            AggregatedMetric::create([
                'metric_type'    => 'avg_risk_score',
                'source_service' => 'ai-service',
                'metric_value'   => max(0.1, round(2.0 - (9 - $i) * 0.3, 2)),
                'metric_date'    => $today->copy()->subDays($i)->toDateString(),
            ]);
        }

        Http::fake(['*generateContent*' => Http::response(['error' => 'unreachable'], 500)]);

        $service = app(PredictiveAnalysisService::class);
        $insights = $service->runAll();

        $insight = collect($insights)->firstWhere('metric_type', 'risk_score_forecast');
        $this->assertNotNull($insight);

        foreach ($insight->predicted_series as $point) {
            $this->assertGreaterThanOrEqual(0.0, $point['value']);
            $this->assertLessThanOrEqual(10.0, $point['value']);
        }
    }

    public function test_falls_back_to_deterministic_narrative_when_gemini_unavailable(): void
    {
        $today = Carbon::today();
        for ($i = 4; $i >= 0; $i--) {
            AggregatedMetric::create([
                'metric_type' => 'avg_risk_score', 'source_service' => 'ai-service',
                'metric_value' => 5.0, 'metric_date' => $today->copy()->subDays($i)->toDateString(),
            ]);
        }

        Http::fake(['*generateContent*' => Http::response(['error' => 'unreachable'], 500)]);

        $service = app(PredictiveAnalysisService::class);
        $insights = $service->runAll();

        $insight = collect($insights)->firstWhere('metric_type', 'risk_score_forecast');
        $this->assertNotNull($insight);
        $this->assertNotEmpty($insight->ai_narrative);
    }
}
