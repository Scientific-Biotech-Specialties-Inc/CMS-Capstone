<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-026: AI Risk Assessment — playbook clause library.
 *
 * The source-of-truth standard clauses that contract text is compared
 * against during the RAG risk-assessment pipeline. Seeded via
 * PlaybookClauseSeeder (draft content — see
 * IMPLEMENTATION_PLAN_AI_RISK_VENDOR_ANALYTICS.md for the general-terms
 * draft and the note that it needs SBSI/legal review before being treated
 * as authoritative).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playbook_clauses', function (Blueprint $table) {
            $table->id();
            $table->string('clause_code', 30)->unique();
            $table->string('title', 150);
            $table->text('standard_text');
            $table->string('category', 100)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playbook_clauses');
    }
};
