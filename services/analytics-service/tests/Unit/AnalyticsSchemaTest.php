<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnalyticsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_candidate_tables_and_columns_exist_after_migrations(): void
    {
        $this->assertTrue(Schema::hasTable('aggregated_metrics'));
        $this->assertTrue(Schema::hasColumns('aggregated_metrics', [
            'id',
            'metric_type',
            'source_service',
            'source_record_id',
            'metric_value',
            'metric_date',
            'metadata',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasTable('reports'));
        $this->assertTrue(Schema::hasColumns('reports', [
            'id',
            'report_type',
            'source_service',
            'source_record_id',
            'generated_by',
            'parameters',
            'file_path',
            'status',
            'generated_at',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasTable('audit_logs'));
        $this->assertTrue(Schema::hasColumns('audit_logs', [
            'audit_id',
            'action',
            'entity_type',
            'entity_id',
            'user_id',
            'old_data',
            'new_data',
            'performed_at',
            'user_name',
            'user_email',
            'user_role',
            'user_department',
        ]));
    }
}
