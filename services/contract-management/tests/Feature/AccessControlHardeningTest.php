<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractCategory;
use App\Models\ContractStatus;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccessControlHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected int $currentAuthUserId = 42;
    protected string $currentAuthUserRole = 'Sales';
    protected array $currentAuthPermissions = [];

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::store('file')->flush();
        Bus::fake([
            \App\Jobs\SyncContractToMeilisearch::class,
            \App\Jobs\RemoveContractFromMeilisearch::class,
        ]);
        Storage::fake(config('filesystems.default', 'local'));

        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => function ($request) {
                return Http::response([
                    'valid' => true,
                    'user' => [
                        'id' => $this->currentAuthUserId,
                        'email' => "user{$this->currentAuthUserId}@sbsi.com",
                        'first_name' => 'Test',
                        'last_name' => "User{$this->currentAuthUserId}",
                        'role' => $this->currentAuthUserRole,
                        'permissions' => $this->currentAuthPermissions,
                        'department' => 'Sales & Marketing',
                    ]
                ], 200);
            },
            'http://notification:8000/api/internal/push' => Http::response(['success' => true], 201),
        ]);
    }

    private function setAuth(int $id, string $role = 'Sales', array $permissions = []): string
    {
        $this->currentAuthUserId = $id;
        $this->currentAuthUserRole = $role;
        $this->currentAuthPermissions = $permissions;
        return "token-user-{$id}-" . uniqid();
    }

    private function createContract(int $creatorId): Contract
    {
        $category = ContractCategory::firstOrCreate(['category_name' => 'Service Agreement']);
        $status = ContractStatus::firstOrCreate(['status_name' => 'Draft']);

        return Contract::create([
            'category_id' => $category->category_id,
            'approval_status_id' => 1,
            'workflow_status_id' => $status->status_id,
            'bp_name' => 'Globe Telecom',
            'item_code' => 'ITM-SEC',
            'description' => 'Security test contract',
            'serial_number' => 'SN-SEC-' . uniqid(),
            'sbu_number' => 'SBU-001',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'created_by' => $creatorId,
        ]);
    }

    public function test_sales_cannot_download_unowned_document(): void
    {
        $contract = $this->createContract(100);

        // Store a fake file on storage disk
        $filePath = 'contracts/documents/test-doc.pdf';
        Storage::disk(config('filesystems.default'))->put($filePath, '%PDF-1.4 mock');

        $document = Document::create([
            'contract_id' => $contract->contract_id,
            'file_name' => 'test-doc.pdf',
            'file_path' => $filePath,
            'file_type' => 'pdf',
            'file_size' => 1024,
            'uploaded_by' => 100,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
        ]);

        $docId = (string) $document->getKey();

        // 1. Sales user 42 (not creator 100) attempts download
        $token42 = $this->setAuth(42, 'Sales', ['cms.contracts.view']);
        $resShow = $this->withHeaders(['Authorization' => "Bearer {$token42}"])
            ->getJson("/api/documents/{$docId}/file");
        $resShow->assertStatus(403);

        $resPresigned = $this->withHeaders(['Authorization' => "Bearer {$token42}"])
            ->getJson("/api/documents/{$docId}/presigned-url");
        $resPresigned->assertStatus(403);

        // 2. Owner user 100 succeeds
        $token100 = $this->setAuth(100, 'Sales', ['cms.contracts.view']);
        $resOwner = $this->withHeaders(['Authorization' => "Bearer {$token100}"])
            ->get("/api/documents/{$docId}/file");
        $resOwner->assertStatus(200);

        Document::destroy($docId);
    }

    public function test_sales_cannot_delete_unowned_contract(): void
    {
        $contract = $this->createContract(100);

        // 1. Sales user 42 attempts to delete
        $token42 = $this->setAuth(42, 'Sales', ['cms.contracts.delete']);
        $response = $this->withHeaders(['Authorization' => "Bearer {$token42}"])
            ->deleteJson("/api/contracts/{$contract->contract_id}");
        $response->assertStatus(403);
        $this->assertDatabaseHas('contracts', ['contract_id' => $contract->contract_id]);

        // 2. Owner user 100 succeeds
        $token100 = $this->setAuth(100, 'Sales', ['cms.contracts.delete']);
        $responseOwner = $this->withHeaders(['Authorization' => "Bearer {$token100}"])
            ->deleteJson("/api/contracts/{$contract->contract_id}");
        $responseOwner->assertStatus(200);
        $this->assertDatabaseMissing('contracts', ['contract_id' => $contract->contract_id]);
    }

    public function test_amendment_creator_cannot_approve_own_amendment(): void
    {
        $contract = $this->createContract(100);

        $amendment = ContractAmendment::create([
            'contract_id'   => $contract->contract_id,
            'bp_name'       => 'Globe Telecom',
            'category'      => 'Service Agreement',
            'item_code'     => 'ITM-SEC',
            'description'   => 'Amendment update',
            'serial_number' => 'SN-AMD-1',
            'sbu_number'    => 'SBU-001',
            'region'        => 'Luzon',
            'start_date'    => '2026-01-01',
            'end_date'      => '2026-12-31',
            'request_date'  => '2026-01-01',
            'status'        => 'Pending',
            'reason'        => 'Scope change',
            'created_by'    => 42,
            'version'       => 1,
        ]);

        // 1. User 42 (creator) with approve permission attempts self-approval
        $token42 = $this->setAuth(42, 'Manager', ['cms.contracts.approve']);
        $response = $this->withHeaders(['Authorization' => "Bearer {$token42}"])
            ->patchJson("/api/contract-amendments/AMD-{$amendment->amendment_id}/status", [
                'status' => 'Approved',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'You cannot review your own amendment request.',
        ]);

        // 2. User 99 (different user) with approve permission succeeds
        $token99 = $this->setAuth(99, 'Manager', ['cms.contracts.approve']);
        $responseOther = $this->withHeaders(['Authorization' => "Bearer {$token99}"])
            ->patchJson("/api/contract-amendments/AMD-{$amendment->amendment_id}/status", [
                'status' => 'Approved',
            ]);

        $responseOther->assertStatus(200);
        $this->assertDatabaseHas('contract_amendments', [
            'amendment_id' => $amendment->amendment_id,
            'status' => 'Approved',
        ]);
    }

    public function test_cannot_hijack_document_belonging_to_another_contract(): void
    {
        // 1. Contract A belongs to user 100 and owns Document A
        $contractA = $this->createContract(100);
        $docA = Document::create([
            'contract_id' => $contractA->contract_id,
            'file_name' => 'docA.pdf',
            'file_path' => 'contracts/documents/docA.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'uploaded_by' => 100,
            'uploaded_at' => now(),
        ]);
        $docAId = (string) $docA->getKey();

        // 2. User 42 attempts to hijack Document A by passing its ID during contract creation
        $token42 = $this->setAuth(42, 'Sales', ['cms.contracts.create', 'cms.contracts.edit']);
        $category = ContractCategory::firstOrCreate(['category_name' => 'Service Agreement']);

        $resCreate = $this->withHeaders(['Authorization' => "Bearer {$token42}"])
            ->postJson('/api/contracts', [
                'businessPartner' => 'Globe Telecom',
                'category' => 'Service Agreement',
                'status' => 'Draft',
                'itemCode' => 'ITM-ATTACK',
                'description' => 'Hijack attempt',
                'serialNo' => 'SN-ATTACK-' . uniqid(),
                'sbuNumber' => 'SBU-001',
                'region' => 'Luzon',
                'startDate' => '2026-01-01',
                'endDate' => '2026-12-31',
                'document_ids' => [$docAId, $this->createDraftPdf()],
            ]);

        $resCreate->assertStatus(201);
        $contractBId = $resCreate->json('data.contract_db_id') ?? $resCreate->json('data.contract_id');

        // Document A must still belong to contract A, NOT contract B
        $docA->refresh();
        $this->assertEquals($contractA->contract_id, $docA->contract_id);

        // 3. User 42 also attempts to hijack Document A via contract update
        $resUpdate = $this->withHeaders(['Authorization' => "Bearer {$token42}"])
            ->putJson("/api/contracts/{$contractBId}", [
                'businessPartner' => 'Globe Telecom',
                'category' => 'Service Agreement',
                'status' => 'Draft',
                'itemCode' => 'ITM-ATTACK',
                'description' => 'Hijack attempt update',
                'serialNo' => 'SN-ATTACK-2-' . uniqid(),
                'sbuNumber' => 'SBU-001',
                'region' => 'Luzon',
                'startDate' => '2026-01-01',
                'endDate' => '2026-12-31',
                'document_ids' => [$docAId],
            ]);

        $resUpdate->assertStatus(200);

        // Document A must STILL belong to contract A
        $docA->refresh();
        $this->assertEquals($contractA->contract_id, $docA->contract_id);

        Document::destroy($docAId);
    }

    public function test_sales_cannot_submit_amendment_or_view_versions_for_unowned_contract(): void
    {
        $contract = $this->createContract(100);

        // 1. Sales user 42 attempts to submit amendment for user 100's contract
        $token42 = $this->setAuth(42, 'Sales', ['cms.contracts.create', 'cms.contracts.view']);
        $resStore = $this->withHeaders(['Authorization' => "Bearer {$token42}"])
            ->postJson('/api/contract-amendments', [
                'contract_id'   => $contract->contract_id,
                'bp_name'       => 'Globe Telecom',
                'category'      => 'Service Agreement',
                'item_code'     => 'ITM-SEC',
                'description'   => 'Unauthorized amendment',
                'serial_number' => 'SN-UNAUTH-' . uniqid(),
                'sbu_number'    => 'SBU-001',
                'region'        => 'Luzon',
                'start_date'    => '2026-01-01',
                'end_date'      => '2026-12-31',
                'reason'        => 'Unauthorized reason',
            ]);

        $resStore->assertStatus(403);

        // 2. Sales user 42 attempts to view version history
        $resVersions = $this->withHeaders(['Authorization' => "Bearer {$token42}"])
            ->getJson("/api/contracts/{$contract->contract_id}/versions");

        $resVersions->assertStatus(403);

        // 3. Owner user 100 succeeds on both
        $token100 = $this->setAuth(100, 'Sales', ['cms.contracts.create', 'cms.contracts.view']);
        $resOwnerStore = $this->withHeaders(['Authorization' => "Bearer {$token100}"])
            ->postJson('/api/contract-amendments', [
                'contract_id'   => $contract->contract_id,
                'bp_name'       => 'Globe Telecom',
                'category'      => 'Service Agreement',
                'item_code'     => 'ITM-SEC',
                'description'   => 'Owner amendment',
                'serial_number' => 'SN-AUTH-' . uniqid(),
                'sbu_number'    => 'SBU-001',
                'region'        => 'Luzon',
                'start_date'    => '2026-01-01',
                'end_date'      => '2026-12-31',
                'reason'        => 'Owner reason',
            ]);

        $resOwnerStore->assertStatus(201);

        $resOwnerVersions = $this->withHeaders(['Authorization' => "Bearer {$token100}"])
            ->getJson("/api/contracts/{$contract->contract_id}/versions");

        $resOwnerVersions->assertStatus(200);
    }

    public function test_workflow_engine_enforces_sod_when_enabled(): void
    {
        config([
            'services.features.workflow_engine_enabled' => true,
            'services.features.enforce_sod' => true,
        ]);

        $category = ContractCategory::firstOrCreate(['category_name' => 'Service Agreement']);
        $contract = $this->createContract(42);

        $workflow = \App\Models\Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'Approval SoD Chain',
            'status' => 'active',
            'resubmit_mode' => 'restart',
        ]);
        $step = \App\Models\WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'name' => 'Step 1',
            'order_index' => 0,
            'mode' => 'sequential',
        ]);
        \App\Models\WorkflowStepRole::create([
            'step_id' => $step->id,
            'auth_role_id' => 350,
            'role_name_snapshot' => 'Approver',
        ]);

        $instance = \App\Models\ApprovalInstance::create([
            'contract_id' => $contract->contract_id,
            'workflow_id' => $workflow->id,
            'run_number' => 1,
            'status' => 'in_progress',
            'current_step_id' => $step->id,
        ]);
        $task = \App\Models\ApprovalTask::create([
            'instance_id' => $instance->id,
            'step_id' => $step->id,
            'auth_role_id' => 350,
            'status' => 'pending',
        ]);

        // Mock auth-service active-holders to return user 42
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => function ($request) {
                return Http::response([
                    'valid' => true,
                    'user' => [
                        'id' => $this->currentAuthUserId,
                        'email' => "user{$this->currentAuthUserId}@sbsi.com",
                        'first_name' => 'Test',
                        'last_name' => "User{$this->currentAuthUserId}",
                        'role' => 'Manager',
                        'permissions' => ['cms.contracts.approve'],
                        'department' => 'Sales & Marketing',
                    ]
                ], 200);
            },
            'http://auth-service:8000/api/internal/roles/350/active-holders' => Http::response([
                'data' => [['id' => 42, 'email' => 'user42@sbsi.com', 'first_name' => 'User', 'last_name' => '42', 'department' => 'Sales & Marketing']],
                'empty' => false,
            ], 200),
            'http://notification:8000/api/internal/push' => Http::response(['success' => true], 201),
        ]);

        // User 42 created contract 42. Attempting to approve own contract through engine must fail 403
        $token42 = $this->setAuth(42, 'Manager', ['cms.contracts.approve']);
        $response = $this->withHeaders(['Authorization' => "Bearer {$token42}"])
            ->postJson("/api/contracts/{$contract->contract_id}/approval-tasks/{$task->id}/decision", [
                'decision' => 'approved',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'You cannot approve or reject a contract you created.',
        ]);
    }

    public function test_sales_without_approve_permission_cannot_view_unowned_approval_progress(): void
    {
        $contract = $this->createContract(100);

        // 1. Sales user 42 without approve permission receives 403
        $token42 = $this->setAuth(42, 'Sales', ['cms.contracts.view']);
        $response = $this->withHeaders(['Authorization' => "Bearer {$token42}"])
            ->getJson("/api/contracts/{$contract->contract_id}/approval-progress");

        $response->assertStatus(403);

        // 2. Owner user 100 succeeds (returns engine: false since no instance created)
        $token100 = $this->setAuth(100, 'Sales', ['cms.contracts.view']);
        $resOwner = $this->withHeaders(['Authorization' => "Bearer {$token100}"])
            ->getJson("/api/contracts/{$contract->contract_id}/approval-progress");

        $resOwner->assertStatus(200);
        $resOwner->assertJsonPath('data.engine', false);
    }
}
