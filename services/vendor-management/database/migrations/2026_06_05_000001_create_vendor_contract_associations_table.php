<?php

declare(strict_types=1);

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
        Schema::create('vendor_contract_associations', function (Blueprint $table) {
            $table->id();
            $table->enum('vendor_type', ['supplier', 'partner']);
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('contract_id');
            $table->unsignedInteger('attached_by')->nullable();
            $table->timestamps();

            $table->unique(['vendor_type', 'vendor_id', 'contract_id'], 'vendor_contract_unique');
            $table->index(['vendor_type', 'vendor_id'], 'vendor_contract_index');
            $table->index('contract_id');
            $table->index('attached_by');
            $table->foreign('contract_id')
                  ->references('contract_id')
                  ->on('contracts')
                  ->onDelete('cascade');
        });
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('vendor_contract_associations', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
        });
        Schema::dropIfExists('vendor_contract_associations');
        Schema::enableForeignKeyConstraints();
    }
};
