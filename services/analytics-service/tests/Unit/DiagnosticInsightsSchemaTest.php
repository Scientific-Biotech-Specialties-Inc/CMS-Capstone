<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DiagnosticInsightsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnostic_insights_table_exists_after_migrations(): void
    {
        $this->assertTrue(Schema::hasTable('diagnostic_insights'));
        $this->assertTrue(Schema::hasColumns('diagnostic_insights', [
            'id', 'metric_type', 'period_start', 'period_end',
            'finding_summary', 'ai_narrative', 'generated_at',
            'created_at', 'updated_at',
        ]));
    }
}
