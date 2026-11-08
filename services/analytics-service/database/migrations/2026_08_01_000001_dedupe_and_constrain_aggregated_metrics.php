<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data-integrity fix for the analytics predictive pipeline:
 *
 * 1. Duplicate (metric_type, source_service, metric_date) rows had
 *    accumulated in aggregated_metrics (demo seeder + live aggregation
 *    both writing for the same historical dates), which fed corrupted,
 *    double-counted points into the 30-day forecast's historical series.
 * 2. Some avg_risk_score rows were written on the raw 0-100 severity-
 *    weighted scale used internally by ai-service's risk assessment
 *    pipeline, instead of the 0-10 scale analytics documents and charts
 *    assume — producing values like 37.65/42.37 next to normal 5-7
 *    readings. This backfills those legacy rows; the write path itself is
 *    fixed in DescriptiveAggregationService so it won't recur.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop older duplicate rows, keeping the highest id (most recent
        // write) per (metric_type, source_service, metric_date) group.
        // Written as a portable subquery (rather than a MySQL-specific
        // multi-table DELETE JOIN) so it also runs under SQLite in tests.
        DB::statement(<<<'SQL'
            DELETE FROM aggregated_metrics
            WHERE id NOT IN (
                SELECT keep_id FROM (
                    SELECT MAX(id) AS keep_id
                    FROM aggregated_metrics
                    GROUP BY metric_type, source_service, metric_date
                ) keepers
            )
        SQL);

        // 2. Backfill legacy avg_risk_score rows that were written on the
        // wrong (0-100) scale. Any value above 10 on a metric documented as
        // 0-10 is unambiguously a pre-fix write.
        DB::table('aggregated_metrics')
            ->where('metric_type', 'avg_risk_score')
            ->where('metric_value', '>', 10)
            ->update(['metric_value' => DB::raw('ROUND(metric_value / 10, 2)')]);

        // 3. Prevent this class of duplicate from recurring.
        Schema::table('aggregated_metrics', function (Blueprint $table) {
            $table->unique(['metric_type', 'source_service', 'metric_date'], 'aggregated_metrics_unique_daily_point');
        });
    }

    public function down(): void
    {
        Schema::table('aggregated_metrics', function (Blueprint $table) {
            $table->dropUnique('aggregated_metrics_unique_daily_point');
        });
    }
};
