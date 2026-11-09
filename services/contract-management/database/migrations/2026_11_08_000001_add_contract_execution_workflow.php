<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->boolean('execution_required')->default(false);
            $table->string('execution_draft_id')->nullable();
            $table->string('signed_document_id')->nullable();
            $table->string('notarized_document_id')->nullable();
            $table->timestamp('revision_paused_at')->nullable();
            $table->timestamp('internal_approved_at')->nullable();
        });

        Schema::table('workflows', function (Blueprint $table): void {
            $table->json('execution_reviewers')->nullable();
        });

        Schema::table('approval_instances', function (Blueprint $table): void {
            $table->string('draft_document_id')->nullable();
        });

        // SQLite mirrors the MongoDB document model in the feature tests.
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('documents', function (Blueprint $table): void {
                $table->string('doc_category')->nullable();
                $table->string('signature_method')->nullable();
                $table->string('replaces_document_id')->nullable();
                $table->json('signature_evidence')->nullable();
            });
        }

        Schema::create('contract_execution_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->foreign('contract_id')->references('contract_id')->on('contracts')->cascadeOnDelete();
            $table->uuid('operation_id');
            $table->string('action', 24);
            $table->string('stage', 24);
            $table->string('state', 24);
            $table->string('draft_document_id')->nullable();
            $table->string('document_id')->nullable();
            $table->unsignedBigInteger('submitted_by');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['contract_id', 'operation_id']);
            $table->index(['contract_id', 'state', 'id']);
        });

        foreach (['SBSI Review', 'Client Review', 'For Signing', 'Signed', 'Notarized PDF'] as $stage) {
            if (!DB::table('contract_statuses')->where('status_name', $stage)->exists()) {
                DB::table('contract_statuses')->insert(['status_name' => $stage]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_execution_events');
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('documents', fn (Blueprint $table) => $table->dropColumn([
                'doc_category', 'signature_method', 'replaces_document_id', 'signature_evidence',
            ]));
        }
        Schema::table('approval_instances', fn (Blueprint $table) => $table->dropColumn('draft_document_id'));
        Schema::table('workflows', fn (Blueprint $table) => $table->dropColumn('execution_reviewers'));
        Schema::table('contracts', fn (Blueprint $table) => $table->dropColumn([
            'execution_required', 'execution_draft_id', 'signed_document_id',
            'notarized_document_id', 'revision_paused_at',
            'internal_approved_at',
        ]));
    }
};
