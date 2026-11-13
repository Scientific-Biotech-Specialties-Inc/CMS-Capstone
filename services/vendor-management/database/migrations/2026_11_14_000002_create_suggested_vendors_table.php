<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggested_vendors', function (Blueprint $table): void {
            $table->id('suggested_vendor_id');
            $table->string('vendor_name');
            $table->string('tin_number', 100)->nullable()->index();
            $table->string('industry', 100)->nullable();
            $table->string('contact_person')->nullable();
            $table->text('contact_number')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('region', 100)->nullable();
            $table->string('default_expense_category', 100)->nullable();
            $table->string('source_system', 50)->default('SERMS')->index();
            $table->string('source_receipt_id', 100)->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->string('status', 30)->default('Pending')->index();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('created_supplier_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggested_vendors');
    }
};
