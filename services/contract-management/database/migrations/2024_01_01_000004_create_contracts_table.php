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
        Schema::disableForeignKeyConstraints();
        Schema::create('contracts', function (Blueprint $table) {
            $table->id('contract_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->string('bp_name')->nullable();
            $table->string('sbu_number')->nullable();
            $table->string('item_code')->nullable();
            $table->text('description')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // From auth-service
            $table->timestamps();

            // Foreign Keys
            $table->foreign('category_id')->references('category_id')->on('contract_categories')->onDelete('set null');
            $table->foreign('supplier_id')->references('supplier_id')->on('suppliers')->onDelete('set null');
            $table->foreign('status_id')->references('status_id')->on('contract_statuses')->onDelete('set null');
        });
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('contracts');
        Schema::enableForeignKeyConstraints();
    }
};
