<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_extractions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->nullable()->index(); // soft ref: contract-management documents.document_id
            $table->unsignedBigInteger('contract_id')->nullable()->index(); // soft ref: contract-management contracts.contract_id
            $table->jsonb('extracted_fields')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, completed, failed
            $table->timestamp('extracted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_extractions');
    }
};
