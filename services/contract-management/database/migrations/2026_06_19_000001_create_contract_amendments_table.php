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
        Schema::create('contract_amendments', function (Blueprint $table) {
            $table->id('amendment_id');
            $table->unsignedBigInteger('contract_id');
            $table->text('bp_name');
            $table->string('category');
            $table->string('item_code');
            $table->text('description');
            $table->string('serial_number');
            $table->string('sbu_number');
            $table->string('region');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->string('status')->default('Pending');
            $table->date('request_date');
            $table->integer('version');
            $table->unsignedBigInteger('created_by'); // user ID from auth-service
            $table->string('approved_by')->nullable(); // Manager name
            $table->text('rejection_reason')->nullable();
            $table->json('document_ids')->nullable(); // Array of MongoDB document IDs
            $table->timestamps();

            $table->foreign('contract_id')->references('contract_id')->on('contracts')->onDelete('cascade');
        });

        Schema::create('contract_version_snapshots', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('contract_id');
            $table->integer('version');
            $table->text('bp_name');
            $table->string('category');
            $table->string('item_code');
            $table->text('description');
            $table->string('serial_number');
            $table->string('sbu_number');
            $table->string('region');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->string('amended_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->date('approved_date')->nullable();
            $table->json('docs')->nullable(); // Complete array of mapped documents
            $table->timestamps();

            $table->foreign('contract_id')->references('contract_id')->on('contracts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_version_snapshots');
        Schema::dropIfExists('contract_amendments');
    }
};
