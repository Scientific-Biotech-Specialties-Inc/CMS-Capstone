<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aggregated_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type', 100)->index();
            $table->string('source_service', 50)->index();
            $table->unsignedBigInteger('source_record_id')->nullable()->index();
            $table->decimal('metric_value', 18, 4)->nullable();
            $table->date('metric_date')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_service', 'metric_type', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregated_metrics');
    }
};
