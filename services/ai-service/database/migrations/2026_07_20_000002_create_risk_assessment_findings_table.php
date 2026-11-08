<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-026: AI Risk Assessment — one row per flagged clause deviation.
 *
 * Replaces relying solely on the `findings` jsonb blob on
 * risk_assessment_results so each finding's severity, playbook citation,
 * deviation reason, and remediation are queryable/renderable individually —
 * required for the Manager-facing summary screen and PDF export (US-026 AC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessment_findings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('risk_assessment_result_id')->index();
            $table->text('clause_reference'); // the actual contract text/section flagged
            $table->string('severity', 20)->index(); // low, medium, high, critical
            $table->unsignedBigInteger('playbook_clause_id')->nullable()->index();
            $table->decimal('retrieval_score', 5, 4)->nullable(); // cosine similarity, kept for explainability
            $table->text('deviation_reason');
            $table->text('recommended_remediation');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('risk_assessment_result_id')
                ->references('id')->on('risk_assessment_results')
                ->onDelete('cascade');
            $table->foreign('playbook_clause_id')
                ->references('id')->on('playbook_clauses')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessment_findings');
    }
};
