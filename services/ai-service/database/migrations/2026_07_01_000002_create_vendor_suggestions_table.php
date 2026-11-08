<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_type', 20)->nullable()->index();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->unsignedBigInteger('contract_id')->nullable()->index();
            $table->decimal('suggestion_score', 5, 2)->nullable();
            $table->text('suggestion_reason')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('suggested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_suggestions');
    }
};
