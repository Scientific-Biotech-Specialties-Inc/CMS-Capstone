<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 4: Analytics — diagnostic layer. Stores the algorithmic
 * root-cause/correlation breakdown ("what changed and where") for a given
 * metric + period, plus an optional Gemini-generated plain-language
 * narrative summarizing that already-computed structured result (the AI's
 * role is narrow: explain facts, not derive them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_insights', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type', 100)->index(); // e.g. 'risk_flag_rate', 'approval_sla_bottleneck'
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->json('finding_summary'); // structured root-cause/correlation result
            $table->text('ai_narrative')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_insights');
    }
};
