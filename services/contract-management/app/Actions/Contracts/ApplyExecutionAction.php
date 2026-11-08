<?php

declare(strict_types=1);

namespace App\Actions\Contracts;

use App\Models\Contract;
use App\Models\ContractApprovalStatus;
use App\Models\ContractExecutionEvent;
use App\Models\ContractStatus;
use App\Models\Document;
use App\Policies\ContractExecutionPolicy;
use App\Services\ApprovalEngineService;
use App\Services\MalwareScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class ApplyExecutionAction
{
    public const NEXT = [
        'SBSI Review' => 'Client Review',
        'Client Review' => 'For Signing',
        'For Signing' => 'Signed',
        'Signed' => 'Notarized PDF',
    ];

    public function __construct(
        private ContractExecutionPolicy $policy,
        private ApprovalEngineService $approval,
        private MalwareScannerService $scanner,
    ) {}

    public function handle(Contract $contract, Request $request, array $input): ContractExecutionEvent
    {
        $this->autoHeal($contract);
        $action = $input['action'];
        $stage = $input['stage'];
        $actor = (int) $request->input('auth_id');
        $fileCategory = match (true) {
            $action === 'revise' => 'draft',
            $action === 'submit' && $stage === 'For Signing' => 'signed',
            $action === 'submit' && $stage === 'Signed' => 'notarized',
            default => null,
        };
        $canReview = in_array($action, ['confirm', 'return'], true)
            || ($action === 'submit' && $stage === 'For Signing' && $this->policy->hasBypass($request));
        $canConfirm = $canReview && $this->policy->canConfirm($request, $this->reviewers($contract), $stage);
        $enforceSod = (bool) config('services.features.enforce_sod');
        $owns = $this->policy->owns($request, $contract);
        $canSubmit = $this->policy->canSubmit($request, $contract);

        abort_unless(match ($action) {
            'confirm', 'return' => $canConfirm && (!$enforceSod || $this->policy->hasBypass($request) || !$owns),
            'pause', 'resume' => $this->policy->canManageRevision($request, $contract),
            default => $canSubmit,
        }, 403);

        $previous = $contract->executionEvents()->where('operation_id', $input['operation_id'])->first();
        if ($previous) {
            abort_unless($previous->action === $action && (int) $previous->submitted_by === $actor, 409);
            return $previous;
        }

        $scanStatus = $fileCategory ? $this->checkFile($input, $fileCategory) : null;
        $disk = Storage::disk(config('filesystems.default', 'local'));
        $path = null;
        $document = null;

        try {
            return DB::transaction(function () use ($contract, $request, $input, $action, $stage, $actor, $fileCategory, $canConfirm, $scanStatus, $disk, $enforceSod, &$path, &$document) {
                $contract = Contract::with(['workflowStatus', 'approvalStatus'])->lockForUpdate()->findOrFail($contract->getKey());
                $this->autoHeal($contract);
                $previous = $contract->executionEvents()->where('operation_id', $input['operation_id'])->first();
                if ($previous) {
                    abort_unless($previous->action === $action && (int) $previous->submitted_by === $actor, 409);
                    return $previous;
                }

                $draftMatches = empty($contract->execution_draft_id) || empty($input['draft_document_id'])
                    || $contract->execution_draft_id === $input['draft_document_id'];

                abort_unless($contract->execution_required && $contract->approvalStatus?->status_name === 'Approved'
                    && $contract->workflowStatus?->status_name === $stage
                    && $draftMatches, 409, 'The contract stage or draft changed. Refresh and try again.');
                $revision = in_array($action, ['pause', 'resume', 'revise'], true);
                abort_if($revision && $contract->signed_document_id, 409, 'Signed contracts cannot be paused for revision.');
                $paused = $contract->revision_paused_at !== null;
                abort_unless($paused === in_array($action, ['resume', 'revise'], true), 409, 'The contract revision state changed.');

                $pending = $contract->executionEvents()->where('state', 'pending')->first();
                if ($action === 'submit') {
                    abort_if($pending, 409, 'A submission is already awaiting confirmation.');
                }
                if (in_array($action, ['confirm', 'return'], true)) {
                    $pendingDraftMatches = empty($pending->draft_document_id) || empty($contract->execution_draft_id)
                        || $pending->draft_document_id === $contract->execution_draft_id;
                    abort_unless($pending && $pending->id === (int) $input['submission_id'] && $pending->stage === $stage
                        && $pendingDraftMatches, 409, 'The submission has changed.');
                    abort_if($enforceSod && !$this->policy->hasBypass($request) && (int) $pending->submitted_by === $actor, 403);
                }

                if (in_array($action, ['submit', 'resume'], true)) {
                    if ($contract->execution_draft_id) {
                        abort_unless($this->currentDraft($contract), 409, 'The approved draft PDF is unavailable.');
                    }
                }

                if ($fileCategory) {
                    $file = $input['file'];
                    $uuid = (string) Str::uuid();
                    $folder = $fileCategory === 'draft' ? 'contracts/documents' : "contracts/{$fileCategory}";
                    $path = "{$folder}/{$uuid}.pdf";
                    abort_unless($disk->putFileAs($folder, $file, "{$uuid}.pdf"), 503, 'The PDF could not be stored.');
                    $document = Document::create([
                        'contract_id' => (int) $contract->getKey(), 'uuid' => $uuid,
                        'file_name' => $file->getClientOriginalName(), 'file_path' => $path,
                        'file_type' => 'pdf', 'file_size' => $file->getSize(),
                        'uploaded_by' => $actor, 'uploaded_at' => now(), 'scan_status' => $scanStatus,
                        'doc_category' => $fileCategory, 'signature_method' => $input['method'] ?? null,
                        'replaces_document_id' => $fileCategory === 'notarized' ? $contract->signed_document_id : $contract->execution_draft_id,
                        'signature_evidence' => ($input['method'] ?? null) === 'e_signature' ? [
                            'actor_id' => $actor, 'signer_name' => $input['signer_name'],
                            'page' => (int) $input['signature_page'], 'x' => (float) $input['signature_x'], 'y' => (float) $input['signature_y'],
                            'width' => isset($input['signature_width']) ? (float) $input['signature_width'] : 0.25,
                            'signed_at' => now()->toISOString(), 'sha256' => hash_file('sha256', $file->getRealPath()),
                        ] : null,
                    ]);
                }

                $autoConfirm = $action === 'submit' && $this->policy->hasBypass($request) && ($stage !== 'For Signing' || $canConfirm);
                $event = $contract->executionEvents()->create([
                    'operation_id' => $input['operation_id'], 'action' => $action, 'stage' => $stage,
                    'state' => $action === 'submit' ? ($autoConfirm ? 'confirmed' : 'pending') : 'recorded',
                    'draft_document_id' => $contract->execution_draft_id,
                    'document_id' => $document ? (string) $document->getKey() : $pending?->document_id,
                    'submitted_by' => $actor, 'reason' => $input['reason'] ?? null,
                    'decided_by' => $autoConfirm ? $actor : null, 'decided_at' => $autoConfirm ? now() : null,
                ]);

                if ($autoConfirm || $action === 'confirm') {
                    $this->advance($contract, $stage, $event->document_id);
                }
                if (in_array($action, ['confirm', 'return'], true)) {
                    $pending->update([
                        'state' => $action === 'confirm' ? 'confirmed' : 'returned',
                        'decided_by' => $actor, 'decided_at' => now(), 'reason' => $input['reason'] ?? null,
                    ]);
                }
                if ($action === 'pause' || $action === 'resume') {
                    $contract->update(['revision_paused_at' => $action === 'pause' ? now() : null]);
                }
                if ($action === 'revise') {
                    $pending?->update(['state' => 'superseded', 'decided_by' => $actor, 'decided_at' => now()]);
                    $contract->update([
                        'approval_status_id' => ContractApprovalStatus::where('status_name', 'Pending')->value('approval_status_id'),
                        'workflow_status_id' => null, 'revision_paused_at' => null,
                        'execution_draft_id' => $event->document_id, 'rejection_reason' => null, 'notify_manager_count' => 0,
                        'internal_approved_at' => null,
                    ]);
                    $this->approval->resubmit($contract, restartApproved: true);
                }

                return $event;
            });
        } catch (Throwable $error) {
            if ($path) {
                try {
                    $document?->delete();
                    $disk->delete($path);
                } catch (Throwable $cleanupError) {
                    report($cleanupError);
                }
            }
            throw $error;
        }
    }

    public function autoHeal(Contract $contract): void
    {
        if ($contract->approvalStatus?->status_name === 'Approved') {
            $updates = [];
            if (!$contract->execution_required) {
                $updates['execution_required'] = true;
                $contract->execution_required = true;
            }
            if (!$contract->workflow_status_id) {
                $statusId = ContractStatus::where('status_name', 'SBSI Review')->value('status_id');
                if ($statusId) {
                    $updates['workflow_status_id'] = $statusId;
                    $contract->workflow_status_id = $statusId;
                }
            }
            if (empty($contract->execution_draft_id)) {
                $pdfDoc = Document::where('contract_id', (int) $contract->getKey())
                    ->where('file_type', 'pdf')
                    ->latest('_id')
                    ->first();
                if ($pdfDoc) {
                    $updates['execution_draft_id'] = (string) $pdfDoc->getKey();
                    $contract->execution_draft_id = (string) $pdfDoc->getKey();
                }
            }
            if (!empty($updates)) {
                $contract->update($updates);
                $contract->load(['workflowStatus', 'approvalStatus']);
            }
        }
    }

    public function reviewers(Contract $contract): array
    {
        return $contract->approvalInstances()->latest('run_number')->first()?->workflow?->execution_reviewers ?? [];
    }

    private function currentDraft(Contract $contract): ?Document
    {
        return Document::where('contract_id', (int) $contract->getKey())
            ->where('_id', $contract->execution_draft_id)->whereNotNull('file_path')
            ->whereIn('scan_status', ['clean', 'unavailable', 'skipped'])
            ->where('file_type', 'pdf')->first();
    }

    private function checkFile(array $input, string $category): string
    {
        if (!isset($input['file']) || ($category === 'signed' && empty($input['method']))) {
            throw ValidationException::withMessages(['file' => 'A PDF and, for signing, a signing method are required.']);
        }
        if (!config('clamav.enabled', true)) {
            return 'skipped';
        }
        $scan = $this->scanner->scan($input['file']);
        if ($scan->isInfected || $scan->warning) {
            throw ValidationException::withMessages(['file' => $scan->isInfected
                ? 'The PDF failed its malware scan.' : 'The PDF could not be scanned. Try again when scanning is available.']);
        }

        return 'clean';
    }

    private function advance(Contract $contract, string $stage, ?string $documentId): void
    {
        $changes = ['workflow_status_id' => ContractStatus::where('status_name', self::NEXT[$stage])->value('status_id')];
        if (in_array($stage, ['For Signing', 'Signed'], true)) {
            $document = Document::where('contract_id', (int) $contract->getKey())->find($documentId);
            $expectedCategory = $stage === 'For Signing' ? 'signed' : 'notarized';
            abort_unless($document?->doc_category === $expectedCategory && $document->file_path
                && in_array($document->scan_status, ['clean', 'skipped'], true)
                && Storage::disk(config('filesystems.default', 'local'))->exists($document->file_path), 409, 'The submitted PDF is unavailable.');
            $changes[$expectedCategory.'_document_id'] = $documentId;
        }
        $contract->update($changes);
    }
}
