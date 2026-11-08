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
            $table->longText('contact_number')->nullable()->change();
            $table->longText('email')->nullable()->change();
            $table->longText('address')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('contact_number', 255)->nullable()->change();
            $table->string('email', 255)->nullable()->change();
            $table->text('address')->nullable()->change();
        });
    }
};
