<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds `is_active` to the existing `contract_categories` table so a
     * category (contract type) can be retired from new use (e.g. no longer
     * selectable when creating a contract, no longer eligible to have a
     * new approval workflow attached) without deleting it — existing
     * contracts already using it keep resolving the category normally.
     * Defaults to true so all current categories remain usable.
     */
    public function up(): void
    {
        Schema::table('contract_categories', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('category_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_categories', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
