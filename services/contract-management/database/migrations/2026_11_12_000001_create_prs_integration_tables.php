<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('item_code')->unique();
            $table->string('product_name');
            $table->string('sbu_number');
            $table->text('description');
            $table->string('category');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('prs_closing_events', function (Blueprint $table): void {
            $table->uuid('event_id')->primary();
            $table->unsignedBigInteger('prs_activity_id');
            $table->unsignedBigInteger('revision');
            $table->string('outcome', 20);
            $table->timestamp('created_at');
            $table->unique(['prs_activity_id', 'revision']);
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->json('product_snapshot')->nullable();
            $table->unsignedBigInteger('prs_institution_id')->nullable();
            $table->unsignedBigInteger('prs_branch_id')->nullable();
        });

        foreach (['contract_amendments', 'contract_version_snapshots'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->json('product_snapshot')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['contract_amendments', 'contract_version_snapshots'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('product_snapshot'));
        }
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['product_snapshot', 'prs_institution_id', 'prs_branch_id']);
        });
        Schema::dropIfExists('prs_closing_events');
        Schema::dropIfExists('products');
    }
};
