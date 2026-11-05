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
}
