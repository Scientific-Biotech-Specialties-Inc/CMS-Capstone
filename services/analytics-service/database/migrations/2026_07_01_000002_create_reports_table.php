<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 100)->index(); // e.g. 'monthly_contract_summary'
            $table->string('source_service', 50)->nullable()->index();
            $table->unsignedBigInteger('source_record_id')->nullable()->index(); // soft ref to originating record
            $table->unsignedBigInteger('generated_by')->nullable()->index(); // soft ref to users.id (auth-service)
            $table->json('parameters')->nullable();
            $table->string('file_path')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, generated, failed
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
