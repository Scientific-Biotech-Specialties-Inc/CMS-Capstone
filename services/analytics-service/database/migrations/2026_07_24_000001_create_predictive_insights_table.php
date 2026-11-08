<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Predictive Analytics layer. Stores AI-generated forecasts for given metrics,
 * historical trend series, 30-day predicted value points, confidence levels,
 * and executive summary narratives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictive_insights', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type', 100)->index(); // e.g. 'risk_score_forecast', 'approval_time_forecast'
            $table->date('forecast_date')->index();
            $table->integer('forecast_horizon_days')->default(30);
            $table->json('historical_series')->nullable(); // array of { date, value }
            $table->json('predicted_series'); // array of { date, value }
            $table->string('confidence', 20)->default('medium'); // 'high' | 'medium' | 'low'
            $table->text('ai_narrative')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictive_insights');
    }
};
