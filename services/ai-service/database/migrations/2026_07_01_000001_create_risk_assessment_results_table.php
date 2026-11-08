<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessment_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->nullable()->index(); // Soft ref: contract-management documents.document_id.
            $table->unsignedBigInteger('contract_id')->nullable()->index(); // Soft ref: contract-management contracts.contract_id.
            $table->decimal('risk_score', 5, 2)->nullable();
            $table->string('risk_level', 20)->nullable()->index(); // low, medium, high, critical.
            $table->jsonb('findings')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, completed, failed.
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessment_results');
    }
};
