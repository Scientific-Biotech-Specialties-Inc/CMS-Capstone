<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the per-user AI feature toggles referenced in
 * IMPLEMENTATION_PLAN_AI_RISK_VENDOR_ANALYTICS.md:
 * - ai_risk_assessment_enabled: Manager/Sales toggle shown in PreferencesCard,
 *   gates whether Create Contract triggers the AI Risk Assessment RAG pipeline.
 * - ai_vendor_suggestions_enabled: Admin-only toggle, gates the "AI Suggestion"
 *   button on the Vendor Management page (Feature 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_preferences', function (Blueprint $table) {
            $table->boolean('ai_risk_assessment_enabled')->default(true)->after('login_alerts_enabled');
            $table->boolean('ai_vendor_suggestions_enabled')->default(true)->after('ai_risk_assessment_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('email_preferences', function (Blueprint $table) {
            $table->dropColumn(['ai_risk_assessment_enabled', 'ai_vendor_suggestions_enabled']);
        });
    }
};
