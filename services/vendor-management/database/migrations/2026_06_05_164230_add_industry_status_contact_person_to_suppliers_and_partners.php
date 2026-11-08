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
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'industry')) {
                $table->string('industry', 100)->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'status')) {
                $table->string('status', 50)->default('Active');
            }
            if (!Schema::hasColumn('suppliers', 'contact_person')) {
                $table->longText('contact_person')->nullable(); // encrypted column
            }
        });

        Schema::table('business_partners', function (Blueprint $table) {
            if (!Schema::hasColumn('business_partners', 'industry')) {
                $table->string('industry', 100)->nullable();
            }
            if (!Schema::hasColumn('business_partners', 'status')) {
                $table->string('status', 50)->default('Active');
            }
            if (!Schema::hasColumn('business_partners', 'contact_person')) {
                $table->longText('contact_person')->nullable(); // encrypted column
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['industry', 'status', 'contact_person']);
        });

        Schema::table('business_partners', function (Blueprint $table) {
            $table->dropColumn(['industry', 'status', 'contact_person']);
        });
    }
};
