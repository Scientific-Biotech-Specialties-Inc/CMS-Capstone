<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');
            $table->unsignedBigInteger('contract_id')->nullable(); // Depending on exact requirements, could just be generic entity_id
            $table->unsignedBigInteger('user_id'); // From auth-service
            $table->text('message');
            $table->timestamp('notification_date')->useCurrent();
            $table->boolean('is_read')->default(false);

            // We do not add a physical foreign key to contract_id here if contracts are managed in a different service,
            // but since they share 1 database, we *could*. However, it's safer for microservices not to tightly couple via FKs.
            // If the user wants the FK because it's a shared DB:
            // $table->foreign('contract_id')->references('contract_id')->on('contracts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
