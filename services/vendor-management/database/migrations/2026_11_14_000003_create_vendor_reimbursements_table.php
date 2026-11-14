<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_reimbursements', function (Blueprint $table): void {
            $table->id();
            $table->string('vendor_type', 20); // 'supplier' | 'partner'
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('serms_reimbursement_id');
            $table->string('reference_no', 100);
            $table->string('tin_number', 100)->nullable();
            $table->string('vendor_name', 255);
            $table->string('category', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('status', 50)->default('Pending');
            $table->string('employee_name', 255)->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->date('expense_date')->nullable();
            $table->timestamp('serms_created_at')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->index(['vendor_type', 'vendor_id']);
            $table->unique(['vendor_type', 'vendor_id', 'serms_reimbursement_id'], 'vr_vendor_serms_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_reimbursements');
    }
};
