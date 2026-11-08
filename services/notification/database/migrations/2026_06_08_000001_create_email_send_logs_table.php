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
        Schema::create('email_send_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notification_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('recipient_email'); // Encrypted using EncryptedCast
            $table->string('subject');
            $table->string('status', 20)->index(); // 'sent', 'failed', 'skipped'
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            // We do not add a foreign key cascade to notifications.notification_id since
            // they are in separate database domains/concepts or decoupled, but since they share
            // the same DB we could, but index is enough.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_send_logs');
    }
};
