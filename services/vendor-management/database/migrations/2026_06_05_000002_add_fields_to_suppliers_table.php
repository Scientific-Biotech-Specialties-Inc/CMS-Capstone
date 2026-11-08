<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'industry')) {
                $table->string('industry')->nullable()->after('supplier_name');
            }
            if (!Schema::hasColumn('suppliers', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('industry');
            }
            if (!Schema::hasColumn('suppliers', 'status')) {
                $table->string('status', 20)->default('Active')->after('region');
            }
        });

        try {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->index('industry');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->index('status');
            });
        } catch (\Exception $e) {}
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['industry']);
            $table->dropIndex(['status']);
            $table->dropColumn(['industry', 'contact_person', 'status']);
        });
    }
};
