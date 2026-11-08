<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\ContractCategory;
use App\Models\ContractStatus;
use App\Models\Document;
use App\Services\MalwareScanResult;
use App\Services\MalwareScannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class UploadDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\SyncContractToMeilisearch::class,
            \App\Jobs\RemoveContractFromMeilisearch::class,
        ]);
        Storage::fake(config('filesystems.default', 'local'));
        \Illuminate\Support\Facades\Cache::store('file')->flush();
        $this->mockCleanMalwareScan();
    }

    protected function mockCleanMalwareScan(): void
    {
        $this->mock(MalwareScannerService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('scan')
                ->andReturn(MalwareScanResult::clean());
        });
    }

    /**
     * Helper to mock auth service verification for standard users.
     */
    protected function mockAuth(string $role = 'Sales', array $permissions = []): void
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 42,
                    'email' => 'test-user@sbsi.com',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'role' => $role,
                    'permissions' => $permissions,
                    'department' => 'Sales & Marketing',
                ]
            ], 200)
        ]);
    }

    /**
     * Test successful PDF file upload.
     */
    public function test_upload_pdf_document_success()
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->mockAuth('Sales', ['cms.contracts.create']);

        // Create PDF with valid %PDF magic bytes
        $file = UploadedFile::fake()->createWithContent('contract.pdf', "%PDF-1.4\n%EOF");

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->postJson('/api/documents/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Document uploaded. Antivirus scan queued.');
        $response->assertJsonPath('scan_status', 'pending');
        $response->assertJsonStructure([
            'data' => [
                'document_id',
                'file_name',
                'file_type',
                'file_size',
                'document_url',
                'uploaded_by',
                'uploaded_at'
            ]
        ]);

        $data = $response->json()['data'];
        $docId = $data['document_id'];

        // Verify Object Storage
        $filePath = Document::find($docId)->file_path;
        Storage::disk(config('filesystems.default'))->assertExists($filePath);

        // Verify MongoDB metadata record exists
        $this->assertNotNull(Document::find($docId));

        // Verify job was dispatched
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ScanUploadedDocument::class);

        // Verify Audit Log is created in MySQL
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document_uploaded',
            'entity_type' => 'Document',
            'entity_id' => $docId,
            'user_id' => 42,
        ]);

        // Cleanup MongoDB record
        Document::destroy($docId);
    }

    /**
     * Test successful DOCX file upload.
     */
    public function test_upload_docx_document_success()
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->mockAuth('Manager', ['cms.contracts.create']);

        // Create DOCX with valid PK\x03\x04 zip magic bytes
        $file = UploadedFile::fake()->createWithContent('contract.docx', "PK\x03\x04\n");

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->postJson('/api/documents/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('scan_status', 'pending');
        
        $data = $response->json()['data'];
        $docId = $data['document_id'];

        $this->assertNotNull(Document::find($docId));

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ScanUploadedDocument::class);

        // Cleanup MongoDB record
        Document::destroy($docId);
    }

    /**
     * Test upload failure with invalid magic bytes (e.g. text file renamed to pdf).
     */
    public function test_upload_document_invalid_magic_bytes()
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->mockAuth('Sales', ['cms.contracts.create']);

        // Create PDF with plain text content instead of PDF magic bytes
        $file = UploadedFile::fake()->createWithContent('contract.pdf', "This is plain text and not PDF.");

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->postJson('/api/documents/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
        $response->assertJsonFragment([
            'errors' => [
                'file' => ['The file has invalid magic bytes for a PDF document.']
            ]
        ]);
    }

    /**
     * Test the ScanUploadedDocument job when scan is clean.
     */
    public function test_scan_uploaded_document_job_clean()
    {
        $file = UploadedFile::fake()->createWithContent('contract.pdf', "%PDF-1.4\n%EOF");
        $disk = config('filesystems.default', 'local');
        $path = Storage::disk($disk)->putFileAs('contracts/documents', $file, 'test.pdf');

        $document = Document::create([
            'file_name' => 'contract.pdf',
            'file_path' => $path,
            'scan_status' => 'pending',
        ]);

        $mockScanner = \Mockery::mock(MalwareScannerService::class);
        $mockScanner->shouldReceive('scanPath')
            ->once()
            ->andReturn(MalwareScanResult::clean());

        $job = new \App\Jobs\ScanUploadedDocument((string) $document->getKey(), $path);
        $job->handle($mockScanner);

        $document->refresh();
        $this->assertEquals('clean', $document->scan_status);
        Storage::disk($disk)->assertExists($path);
    }

    /**
     * Test the ScanUploadedDocument job when malware is detected.
     */
    public function test_scan_uploaded_document_job_infected()
    {
        $file = UploadedFile::fake()->createWithContent('infected.pdf', "%PDF-1.4\nEICAR");
        $disk = config('filesystems.default', 'local');
        $path = Storage::disk($disk)->putFileAs('contracts/documents', $file, 'test.pdf');

        $document = Document::create([
            'file_name' => 'infected.pdf',
            'file_path' => $path,
            'scan_status' => 'pending',
        ]);

        $mockScanner = \Mockery::mock(MalwareScannerService::class);
        $mockScanner->shouldReceive('scanPath')
            ->once()
            ->andReturn(MalwareScanResult::infected('Eicar-Signature'));

        $job = new \App\Jobs\ScanUploadedDocument((string) $document->getKey(), $path);
        $job->handle($mockScanner);

        $document->refresh();
        $this->assertEquals('infected', $document->scan_status);
        $this->assertEquals('Eicar-Signature', $document->scan_result);
        $this->assertNull($document->file_path);
        Storage::disk($disk)->assertMissing($path);
    }

    /**
     * Test the ScanUploadedDocument job when scanner is unavailable.
     */
    public function test_scan_uploaded_document_job_unavailable()
    {
        $file = UploadedFile::fake()->createWithContent('contract.pdf', "%PDF-1.4\n%EOF");
        $disk = config('filesystems.default', 'local');
        $path = Storage::disk($disk)->putFileAs('contracts/documents', $file, 'test.pdf');

        $document = Document::create([
            'file_name' => 'contract.pdf',
            'file_path' => $path,
            'scan_status' => 'pending',
        ]);

        $mockScanner = \Mockery::mock(MalwareScannerService::class);
        $mockScanner->shouldReceive('scanPath')
            ->once()
            ->andReturn(MalwareScanResult::unavailable('Scanner error'));

        $job = new \App\Jobs\ScanUploadedDocument((string) $document->getKey(), $path);
        $job->handle($mockScanner);

        $document->refresh();
        $this->assertEquals('unavailable', $document->scan_status);
        Storage::disk($disk)->assertExists($path);
    }

    /**
     * Test the ScanUploadedDocument job when disabled via config.
     */
    public function test_scan_uploaded_document_job_skipped_when_disabled()
    {
        $file = UploadedFile::fake()->createWithContent('contract.pdf', "%PDF-1.4\n%EOF");
        $disk = config('filesystems.default', 'local');
        $path = Storage::disk($disk)->putFileAs('contracts/documents', $file, 'test.pdf');

        $document = Document::create([
            'file_name' => 'contract.pdf',
            'file_path' => $path,
            'scan_status' => 'pending',
        ]);

        config(['clamav.enabled' => false]);

        $mockScanner = \Mockery::mock(MalwareScannerService::class);
        $mockScanner->shouldNotReceive('scanPath');

        $job = new \App\Jobs\ScanUploadedDocument((string) $document->getKey(), $path);
        $job->handle($mockScanner);

        $document->refresh();
        $this->assertEquals('skipped', $document->scan_status);
        Storage::disk($disk)->assertExists($path);
    }

    /**
     * Test size limit enforcement (above 10 MB).
     */
    public function test_upload_file_exceeds_size_limit()
    {
        $this->mockAuth('Sales', ['cms.contracts.create']);

        // Create a fake file with 11 MB size
        $file = UploadedFile::fake()->create('heavy.pdf', 11 * 1024, 'application/pdf');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->postJson('/api/documents/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    /**
     * Test linking MongoDB documents to Contract MySQL record.
     */
    public function test_link_documents_to_contract()
    {
        $this->mockAuth('Sales', ['cms.contracts.create']);

        // 1. Create a MongoDB document metadata
        $document = Document::create([
            'file_name' => 'link_test.pdf',
            'file_path' => 'contracts/documents/link_test.pdf',
            'document_url' => 'http://localhost/storage/link_test.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'uploaded_by' => 42,
            'uploaded_at' => now(),
        ]);
        $docId = (string) $document->getKey();

        // 2. Create a contract and link the document_id
        $category = ContractCategory::firstOrCreate(['category_name' => 'Service Agreement']);
        $status = ContractStatus::firstOrCreate(['status_name' => 'Notarized PDF']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->postJson('/api/contracts', [
            'businessPartner' => 'Globe Telecom',
            'category' => 'Service Agreement',
            'status' => 'Notarized PDF',
            'itemCode' => 'ITM-99',
            'description' => 'Test Link Contract',
            'serialNo' => 'SN-99',
            'sbuNumber' => 'SBU-99',
            'region' => 'Luzon',
            'startDate' => '2026-06-01',
            'endDate' => '2026-07-01',
            'document_ids' => [$docId]
        ]);

        $response->assertStatus(201);
        $contractId = $response->json()['data']['contract_db_id'] ?? $response->json()['data']['contract_id'];

        // Verify the MongoDB document now has the correct contract_id
        $updatedDoc = Document::find($docId);
        $this->assertEquals($contractId, $updatedDoc->contract_id);

        // Verify relational retrieval
        $contract = Contract::find($contractId);
        $this->assertCount(1, $contract->documents);
        $this->assertEquals($docId, (string) $contract->documents->first()->getKey());

        // Cleanup
        Document::destroy($docId);
    }

    /**
     * Test that updating a contract with a reduced set of document_ids deletes the removed document.
     */
    public function test_update_contract_deletes_removed_document()
    {
        $this->mockAuth('Sales', ['cms.contracts.create', 'cms.contracts.edit']);

        // 1. Create two MongoDB documents
        $doc1 = Document::create([
            'file_name' => 'doc1.pdf',
            'file_path' => 'contracts/documents/doc1.pdf',
            'document_url' => 'http://localhost/storage/contracts/documents/doc1.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'uploaded_by' => 42,
            'uploaded_at' => now(),
        ]);
        $doc2 = Document::create([
            'file_name' => 'doc2.pdf',
            'file_path' => 'contracts/documents/doc2.pdf',
            'document_url' => 'http://localhost/storage/contracts/documents/doc2.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'uploaded_by' => 42,
            'uploaded_at' => now(),
        ]);

        Storage::disk(config('filesystems.default'))->put('contracts/documents/doc1.pdf', 'content1');
        Storage::disk(config('filesystems.default'))->put('contracts/documents/doc2.pdf', 'content2');

        $doc1Id = (string) $doc1->getKey();
        $doc2Id = (string) $doc2->getKey();

        // 2. Create contract with both documents linked
        $category = ContractCategory::firstOrCreate(['category_name' => 'Service Agreement']);
        $status = ContractStatus::firstOrCreate(['status_name' => 'Notarized PDF']);

        $createResponse = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->postJson('/api/contracts', [
            'businessPartner' => 'Globe Telecom',
            'category' => 'Service Agreement',
            'status' => 'Notarized PDF',
            'itemCode' => 'ITM-99',
            'description' => 'Test Link Contract',
            'serialNo' => 'SN-99',
            'sbuNumber' => 'SBU-99',
            'region' => 'Luzon',
            'startDate' => '2026-06-01',
            'endDate' => '2026-07-01',
            'document_ids' => [$doc1Id, $doc2Id]
        ]);

        $createResponse->assertStatus(201);
        $contractId = $createResponse->json()['data']['contract_db_id'] ?? $createResponse->json()['data']['contract_id'];

        // 3. Verify they are linked
        $this->assertCount(2, Contract::find($contractId)->documents);

        // 4. Update contract, keeping only doc1 (removes doc2)
        $updateResponse = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->putJson("/api/contracts/{$contractId}", [
            'businessPartner' => 'Globe Telecom',
            'category' => 'Service Agreement',
            'status' => 'Notarized PDF',
            'itemCode' => 'ITM-99',
            'description' => 'Test Link Contract',
            'serialNo' => 'SN-99',
            'sbuNumber' => 'SBU-99',
            'region' => 'Luzon',
            'startDate' => '2026-06-01',
            'endDate' => '2026-07-01',
            'document_ids' => [$doc1Id]
        ]);

        $updateResponse->assertStatus(200);

        // 5. Assertions:
        // Contract has 1 document
        $this->assertCount(1, Contract::find($contractId)->documents);
        
        // doc1 exists, doc2 is deleted from MongoDB
        $this->assertNotNull(Document::find($doc1Id));
        $this->assertNull(Document::find($doc2Id));

        // doc1 file exists, doc2 file deleted from storage
        Storage::disk(config('filesystems.default'))->assertExists('contracts/documents/doc1.pdf');
        Storage::disk(config('filesystems.default'))->assertMissing('contracts/documents/doc2.pdf');

        // Audit log of document_deleted exists in MySQL
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document_deleted',
            'entity_type' => 'Document',
            'entity_id' => $doc2Id,
            'user_id' => 42,
        ]);

        // Cleanup
        Document::destroy($doc1Id);
    }

    /**
     * Test that deleting a contract deletes all associated documents and logs the action.
     */
    public function test_delete_contract_deletes_associated_documents()
    {
        $this->mockAuth('Sales', ['cms.contracts.create', 'cms.contracts.delete']);

        // 1. Create a MongoDB document
        $doc = Document::create([
            'file_name' => 'doc.pdf',
            'file_path' => 'contracts/documents/doc.pdf',
            'document_url' => 'http://localhost/storage/contracts/documents/doc.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'uploaded_by' => 42,
            'uploaded_at' => now(),
        ]);
        Storage::disk(config('filesystems.default'))->put('contracts/documents/doc.pdf', 'content');
        $docId = (string) $doc->getKey();

        // 2. Create contract with document linked
        $category = ContractCategory::firstOrCreate(['category_name' => 'Service Agreement']);
        $status = ContractStatus::firstOrCreate(['status_name' => 'Notarized PDF']);

        $createResponse = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->postJson('/api/contracts', [
            'businessPartner' => 'Globe Telecom',
            'category' => 'Service Agreement',
            'status' => 'Notarized PDF',
            'itemCode' => 'ITM-99',
            'description' => 'Test Link Contract',
            'serialNo' => 'SN-99',
            'sbuNumber' => 'SBU-99',
            'region' => 'Luzon',
            'startDate' => '2026-06-01',
            'endDate' => '2026-07-01',
            'document_ids' => [$docId]
        ]);

        $createResponse->assertStatus(201);
        $contractId = $createResponse->json()['data']['contract_id'];

        // 3. Delete contract
        $deleteResponse = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->deleteJson("/api/contracts/{$contractId}");

        $deleteResponse->assertStatus(200);

        // 4. Assertions:
        // Contract is deleted
        $this->assertNull(Contract::find($contractId));

        // Document is deleted from MongoDB
        $this->assertNull(Document::find($docId));

        // File is deleted from storage
        Storage::disk(config('filesystems.default'))->assertMissing('contracts/documents/doc.pdf');

        // Audit log of document_deleted exists in MySQL
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document_deleted',
            'entity_type' => 'Document',
            'entity_id' => $docId,
            'user_id' => 42,
        ]);
    }

    /**
     * Test document upload uses UUID file name.
     */
    public function test_document_upload_uses_uuid_file_name()
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->mockAuth('Sales', ['cms.contracts.create']);

        $file = UploadedFile::fake()->createWithContent('original_name.pdf', "%PDF-1.4\n%EOF");

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->postJson('/api/documents/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $data = $response->json()['data'];
        $docId = $data['document_id'];

        $document = Document::find($docId);
        $this->assertNotNull($document->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $document->uuid
        );

        // Verify path contains UUID filename
        $expectedPath = 'contracts/documents/' . $document->uuid . '.pdf';
        $this->assertEquals($expectedPath, $document->file_path);

        Storage::disk(config('filesystems.default'))->assertExists($expectedPath);

        // Cleanup
        Document::destroy($docId);
    }

    /**
     * Test pre-signed URL generation.
     */
    public function test_generate_presigned_url()
    {
        $this->mockAuth('Sales', ['cms.contracts.view']);

        // Mock temporary URL generation on faked storage disk
        Storage::disk(config('filesystems.default'))->buildTemporaryUrlsUsing(function ($path, $expiration, $options) {
            return 'http://localhost/storage/' . $path . '?signature=test-signature&expires=' . $expiration->getTimestamp();
        });

        $uuid = (string) \Illuminate\Support\Str::uuid();
        $doc = Document::create([
            'uuid' => $uuid,
            'file_name' => 'test.pdf',
            'file_path' => 'contracts/documents/' . $uuid . '.pdf',
            'document_url' => 'http://localhost/storage/contracts/documents/' . $uuid . '.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'uploaded_by' => 42,
            'uploaded_at' => now(),
        ]);
        Storage::disk(config('filesystems.default'))->put($doc->file_path, 'content');
        $docId = (string) $doc->getKey();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->getJson("/api/documents/{$docId}/presigned-url");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'document_id',
            'file_name',
            'presigned_url',
            'expires_at'
        ]);

        $presignedUrl = $response->json()['presigned_url'];
        $this->assertStringContainsString('signature=', $presignedUrl);

        // Cleanup
        Document::destroy($docId);
    }

    /**
     * Test cascade deleting hook on Contract model level.
     */
    public function test_contract_model_deleting_hook_cascades_documents()
    {
        // 1. Create dummy contract in MySQL first to get a valid auto-incremented ID
        $category = ContractCategory::firstOrCreate(['category_name' => 'Service Agreement']);
        $approvalStatus = \App\Models\ContractApprovalStatus::firstOrCreate(['status_name' => 'Pending']);
        $contract = Contract::create([
            'category_id' => $category->category_id,
            'approval_status_id' => $approvalStatus->approval_status_id,
            'bp_name' => 'Cascade BP',
            'item_code' => 'ITM-99',
            'description' => 'Cascade Hook Test',
            'serial_number' => 'SN-9999',
            'sbu_number' => 'SBU-99',
            'region_id' => 1,
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-01',
            'created_by' => 42,
        ]);

        // 2. Create the linked document
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $doc = Document::create([
            'contract_id' => $contract->contract_id,
            'uuid' => $uuid,
            'file_name' => 'cascade.pdf',
            'file_path' => 'contracts/documents/' . $uuid . '.pdf',
            'document_url' => 'http://localhost/storage/contracts/documents/' . $uuid . '.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'uploaded_by' => 42,
            'uploaded_at' => now(),
        ]);
        Storage::disk(config('filesystems.default'))->put($doc->file_path, 'content');
        $docId = (string) $doc->getKey();

        // Verify document is linked in DB
        $this->assertEquals($contract->contract_id, Document::find($docId)->contract_id);
        Storage::disk(config('filesystems.default'))->assertExists($doc->file_path);

        // Delete contract via Eloquent to trigger boot hook
        $contract->delete();

        // Verify document and file are cascade-deleted
        $this->assertNull(Document::find($docId));
        Storage::disk(config('filesystems.default'))->assertMissing($doc->file_path);
    }
}
