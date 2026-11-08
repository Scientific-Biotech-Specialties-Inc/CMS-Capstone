<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor AI Suggestions: extends the already-scaffolded `vendor_suggestions`
 * table to act as the "batch" record for a suggestion request — who asked
 * for it and what industry/region hint they gave — rather than one row per
 * candidate (candidates now live in `vendor_suggestion_candidates`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_suggestions', function (Blueprint $table) {
            $table->unsignedBigInteger('requested_by')->nullable()->after('id');
            $table->string('industry_hint', 150)->nullable()->after('requested_by');
            $table->string('region_hint', 100)->nullable()->after('industry_hint');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_suggestions', function (Blueprint $table) {
            $table->dropColumn(['requested_by', 'industry_hint', 'region_hint']);
        });
    }
};
