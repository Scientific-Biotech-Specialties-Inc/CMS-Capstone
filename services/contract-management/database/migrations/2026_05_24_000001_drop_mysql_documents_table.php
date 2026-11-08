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
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            Schema::dropIfExists('documents');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
                $table->id('_id');
            } else {
                $table->id('document_id');
            }
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->string('uuid')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->string('scan_status')->nullable();
            $table->string('scan_result')->nullable();

            $table->foreign('contract_id')->references('contract_id')->on('contracts')->onDelete('cascade');
        });
    }
};
