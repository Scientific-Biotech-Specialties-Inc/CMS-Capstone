<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_partners', function (Blueprint $table) {
            $table->string('industry')->nullable()->after('partner_name');
            $table->string('contact_person')->nullable()->after('industry');
            $table->string('status', 20)->default('Active')->after('region');
            $table->index('industry');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('business_partners', function (Blueprint $table) {
            $table->dropIndex(['industry']);
            $table->dropIndex(['status']);
            $table->dropColumn(['industry', 'contact_person', 'status']);
        });
    }
};
