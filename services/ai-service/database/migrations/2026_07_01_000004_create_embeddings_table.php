<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep extension setup first so a failure prevents any table changes.
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 20)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->vector('embedding', 1536);
            $table->string('model_name', 100)->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embeddings');
    }
};
