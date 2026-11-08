<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor AI Suggestions: one row per Gemini-suggested candidate company
 * within a `vendor_suggestions` batch. Holds the raw suggested fields
 * (autofill source, not verified data — the frontend lets the user edit
 * before saving) plus the accept/dismiss decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_suggestion_candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_suggestion_id')->index();
            $table->string('candidate_name', 255);
            $table->string('candidate_industry', 150)->nullable();
            $table->string('candidate_region', 100)->nullable();
            $table->string('candidate_contact_email', 255)->nullable();
            $table->string('candidate_contact_number', 50)->nullable();
            $table->text('candidate_address')->nullable();
            $table->decimal('suggestion_score', 5, 2)->nullable();
            $table->text('suggestion_reason')->nullable();
            $table->jsonb('gemini_raw_response')->nullable();
            $table->string('decision', 20)->default('pending')->index(); // pending, accepted, dismissed
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('vendor_suggestion_id')
                ->references('id')->on('vendor_suggestions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_suggestion_candidates');
    }
};
