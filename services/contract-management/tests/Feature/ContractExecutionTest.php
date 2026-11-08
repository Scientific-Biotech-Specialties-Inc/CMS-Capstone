<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncContractToMeilisearch;
use App\Jobs\NotifyExecutionReviewers;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Document;
use App\Policies\ContractExecutionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class ContractExecutionTest extends TestCase
{
    use RefreshDatabase;

    private Contract $contract;
    private string $draft;
    private array $ownerPermissions = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['clamav.enabled' => false]);
        Storage::fake(config('filesystems.default', 'local'));
        Bus::fake([SyncContractToMeilisearch::class, NotifyExecutionReviewers::class]);
        $this->draft = $this->createDraftPdf();
        $this->contract = Contract::create([
            'created_by' => 42, 'execution_required' => true, 'execution_draft_id' => $this->draft,
            'approval_status_id' => 2, 'workflow_status_id' => ContractStatus::where('status_name', 'SBSI Review')->value('status_id'),
            'end_date' => now()->addYear(),
        ]);
        Document::findOrFail($this->draft)->update(['contract_id' => $this->contract->getKey()]);
        Http::fake([
            '*/internal/verify-token' => function ($request) {
                [$id, $role, $extra] = match ($request['token']) {
                    'owner' => [42, 'Custom Sales', $this->ownerPermissions],
                    'manager' => [43, 'Manager', []],
                    'ceo' => [44, 'CEO', []],
                    default => [45, 'Employee', []],
                };
                return Http::response(['valid' => true, 'user' => [
                    'id' => $id, 'role' => $role, 'department' => 'Sales & Marketing',
                    'permissions' => [...$extra, 'cms.contracts.view'],
                ]]);
            },
        ]);
    }

    public function test_only_confirmed_signing_enables_lifecycle_and_notarization_is_optional(): void
    {
        $this->assertSame('pending_execution', $this->contract->lifecycle_status);
        $this->stageAction('owner', 'submit', 'Client Review')->assertConflict();
        $this->stageAction('other', 'submit', 'SBSI Review')->assertForbidden();
        $submission = $this->stageAction('owner', 'submit', 'SBSI Review')->assertOk()->json('data.id');
        $this->stageAction('owner', 'confirm', 'SBSI Review', ['submission_id' => $submission])->assertForbidden();
        $this->stageAction('manager', 'confirm', 'SBSI Review', ['submission_id' => $submission])->assertOk();

        $this->ownerPermissions = [ContractExecutionPolicy::BYPASS];
        $this->stageAction('owner', 'submit', 'Client Review')->assertOk()->assertJsonPath('data.state', 'confirmed');
        $this->stageAction('owner', 'submit', 'For Signing', ['method' => 'scanned_upload'])->assertUnprocessable();
        $signed = $this->stageAction('owner', 'submit', 'For Signing', [
            'method' => 'scanned_upload', 'file' => $this->pdf(),
        ])->assertOk()->assertJsonPath('data.state', 'pending')->json('data');
        $this->assertSame('pending_execution', $this->contract->refresh()->lifecycle_status);
        $this->assertNull($this->contract->signed_document_id);

        $this->stageAction('ceo', 'confirm', 'For Signing', ['submission_id' => $signed['id']])->assertOk();
        $this->assertSame($signed['document_id'], $this->contract->refresh()->signed_document_id);
        $this->assertSame('active', $this->contract->lifecycle_status);
        $this->stageAction('owner', 'submit', 'Signed', ['file' => $this->pdf()])->assertOk();
        $this->assertSame('Notarized PDF', $this->contract->refresh()->workflowStatus->status_name);
    }

    public function test_pause_resumes_the_same_stage_and_replacing_the_pdf_restarts_approval_once(): void
    {
        $this->ownerPermissions = [ContractExecutionPolicy::BYPASS];
        $this->stageAction('owner', 'submit', 'SBSI Review')->assertOk();
        $this->stageAction('manager', 'pause', 'Client Review', ['reason' => 'Client requested a revision'])->assertOk();
        $this->stageAction('owner', 'submit', 'Client Review')->assertConflict();
        $this->stageAction('owner', 'resume', 'Client Review', ['reason' => 'Client accepted existing terms'])->assertOk();
        $this->assertSame('Client Review', $this->contract->refresh()->workflowStatus->status_name);

        $this->stageAction('owner', 'pause', 'Client Review', ['reason' => 'New draft needed'])->assertOk();
        $operation = (string) Str::uuid();
        $first = $this->stageAction('owner', 'revise', 'Client Review', ['operation_id' => $operation, 'file' => $this->pdf()])->assertOk();
        $this->stageAction('owner', 'revise', 'Client Review', ['operation_id' => $operation, 'file' => $this->pdf()])
            ->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertNotSame($this->draft, $this->contract->refresh()->execution_draft_id);
        $this->assertSame('Pending', $this->contract->approvalStatus->status_name);
        $this->assertNull($this->contract->workflow_status_id);
        $this->assertNotNull(Document::find($this->draft));
    }

    public function test_in_app_signature_records_the_selected_page_and_position(): void
    {
        $this->contract->update(['workflow_status_id' => ContractStatus::where('status_name', 'For Signing')->value('status_id')]);
        $submission = $this->stageAction('owner', 'submit', 'For Signing', [
            'method' => 'e_signature', 'file' => $this->pdf(), 'signer_name' => 'Contract Owner',
            'signature_page' => 2, 'signature_x' => 0.4, 'signature_y' => 0.65,
        ])->assertOk()->assertJsonPath('data.state', 'pending')->json('data');
        $document = Document::findOrFail($submission['document_id']);
        $this->assertSame('e_signature', $document->signature_method);
        $this->assertSame($this->draft, $document->replaces_document_id);
        $this->assertSame(2, $document->signature_evidence['page']);
        $this->assertSame(0.4, $document->signature_evidence['x']);
        $this->assertSame(0.65, $document->signature_evidence['y']);
    }

    public function test_pending_signed_pdf_notifies_reviewers_using_the_contract_number(): void
    {
        $this->contract->update(['workflow_status_id' => ContractStatus::where('status_name', 'For Signing')->value('status_id')]);
        $eventId = $this->stageAction('owner', 'submit', 'For Signing', [
            'method' => 'scanned_upload', 'file' => $this->pdf(),
        ])->assertOk()->json('data.id');

        Bus::assertDispatched(NotifyExecutionReviewers::class);
        Http::fake([
            '*/internal/users-by-roles*' => Http::response(['data' => [['id' => 43, 'role' => 'Manager']]]),
            '*/internal/push' => Http::response([], 201),
        ]);
        app()->call([(new NotifyExecutionReviewers($eventId)), 'handle']);

        $number = $this->contract->getKey();
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/internal/push')
            && $request['target_user_id'] === 43
            && $request['message'] === "Contract Number #{$number} with a submitted signed PDF is waiting for confirmation from Manager or CEO.");
    }

    private function stageAction(string $token, string $action, string $stage, array $payload = []): TestResponse
    {
        return $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
            ->post('/api/contracts/'.$this->contract->getKey().'/execution', $payload + [
                'action' => $action, 'stage' => $stage, 'draft_document_id' => $this->draft, 'operation_id' => (string) Str::uuid(),
            ]);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('contract.pdf', "%PDF-1.4\n%%EOF");
    }
}
