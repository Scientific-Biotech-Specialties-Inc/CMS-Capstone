<?php

namespace Tests\Feature;

use App\Models\OcrExtraction;
use App\Services\ContractDocumentClient;
use App\Services\Gemini\GeminiClient;
use App\Services\Ocr\OcrTextExtractor;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Fluent;
use Mockery;
use Tests\TestCase;

final class OcrExtractionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $connection = DB::connection('sqlite');
        $connection->setSchemaGrammar(new class($connection) extends SQLiteGrammar
        {
            protected function typeVector(Fluent $column): string
            {
                return isset($column->dimensions) && $column->dimensions !== ''
                    ? "vector({$column->dimensions})"
                    : 'vector';
            }
        });

        $database = Mockery::mock(DB::getFacadeRoot())->makePartial();
        $database->shouldReceive('statement')
            ->once()
            ->with('CREATE EXTENSION IF NOT EXISTS vector')
            ->andReturnTrue();
        DB::swap($database);

        $this->artisan('migrate:fresh', ['--database' => 'sqlite', '--force' => true])
            ->assertExitCode(0);
    }

    private function fakeAuth(string $role = 'Admin', int $id = 1): void
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user'  => ['id' => $id, 'role' => $role, 'permissions' => []],
            ], 200),
        ]);
    }

    public function test_extraction_requires_at_least_one_source(): void
    {
        $this->fakeAuth();

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/ocr/extract', []);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Either an uploaded file (file) or a pre-uploaded document identifier (document_id) is required.'
        ]);
    }

    public function test_extraction_with_file_upload_succeeds(): void
    {
        $this->fakeAuth();

        $dummyData = [
            'business_partner' => 'Globe Telecom',
            'category' => 'Service Agreement',
            'item_code' => 'ITM-0099',
            'description' => 'OCR Service Contract',
            'serial_number' => 'SN-999-OCR',
            'sbu_number' => 'SBU-777',
            'region' => 'Luzon',
            'start_date' => '2026-08-01',
            'end_date' => '2027-08-01',
            'confidence_score' => 95.5,
        ];

        // Mock Text Extractor
        $extractorMock = Mockery::mock(OcrTextExtractor::class);
        $extractorMock->shouldReceive('extract')
            ->once()
            ->andReturn('This is a mock contract text for Globe Telecom.');
        $this->app->instance(OcrTextExtractor::class, $extractorMock);

        // Mock Gemini Client
        $geminiMock = Mockery::mock(GeminiClient::class);
        $geminiMock->shouldReceive('generateJson')
            ->once()
            ->andReturn($dummyData);
        $this->app->instance(GeminiClient::class, $geminiMock);

        $file = UploadedFile::fake()->createWithContent('contract.pdf', "%PDF-1.4\n%EOF");

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/ocr/extract', [
                'file' => $file,
                'candidate_partners' => ['Globe Telecom', 'Smart Communications']
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['business_partner' => 'Globe Telecom']);

        $this->assertDatabaseHas('ocr_extractions', [
            'status' => 'completed',
            'confidence_score' => 95.5,
        ]);
    }

    public function test_extraction_rejects_disallowed_file_types(): void
    {
        $this->fakeAuth();

        $file = UploadedFile::fake()->createWithContent('script.exe', "MZ\x90\x00");

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/ocr/extract', [
                'file' => $file,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_extraction_by_document_id_rejects_unowned_document_for_sales(): void
    {
        $this->fakeAuth('Sales', 42);

        $docClientMock = Mockery::mock(ContractDocumentClient::class);
        $docClientMock->shouldReceive('getDocumentMetadata')
            ->once()
            ->with('123')
            ->andReturn([
                'document_id' => '123',
                'file_name' => 'smart-contract.pdf',
                'file_type' => 'pdf',
                'contract_created_by' => 999, // Owned by another user
                'uploaded_by' => 999,
            ]);
        $this->app->instance(ContractDocumentClient::class, $docClientMock);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/ocr/extract', [
                'document_id' => '123',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Forbidden.']);
    }

    public function test_extraction_by_document_id_allows_owned_document_for_sales(): void
    {
        $this->fakeAuth('Sales', 42);

        $dummyData = [
            'business_partner' => 'Smart Communications',
            'category' => 'Partnership Agreement',
            'item_code' => 'ITM-0088',
            'description' => 'Internal Integration Agreement',
            'serial_number' => 'SN-888-INTEG',
            'sbu_number' => 'SBU-888',
            'region' => 'Visayas',
            'start_date' => '2026-09-01',
            'end_date' => '2027-09-01',
            'confidence_score' => 88.0,
        ];

        $docClientMock = Mockery::mock(ContractDocumentClient::class);
        $docClientMock->shouldReceive('getDocumentMetadata')
            ->once()
            ->with('123')
            ->andReturn([
                'document_id' => '123',
                'file_name' => 'smart-contract.pdf',
                'file_type' => 'pdf',
                'contract_created_by' => 42, // Owned by current sales user
                'uploaded_by' => 42,
            ]);
        $docClientMock->shouldReceive('fetchFileBytes')
            ->once()
            ->with('123')
            ->andReturn('dummy raw bytes of file');
        $this->app->instance(ContractDocumentClient::class, $docClientMock);

        $extractorMock = Mockery::mock(OcrTextExtractor::class);
        $extractorMock->shouldReceive('extract')
            ->once()
            ->andReturn('This is a mock contract text for Smart Communications.');
        $this->app->instance(OcrTextExtractor::class, $extractorMock);

        $geminiMock = Mockery::mock(GeminiClient::class);
        $geminiMock->shouldReceive('generateJson')
            ->once()
            ->andReturn($dummyData);
        $this->app->instance(GeminiClient::class, $geminiMock);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/ocr/extract', [
                'document_id' => '123',
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['business_partner' => 'Smart Communications']);
    }

    public function test_extraction_with_document_id_succeeds(): void
    {
        $this->fakeAuth();

        $dummyData = [
            'business_partner' => 'Smart Communications',
            'category' => 'Partnership Agreement',
            'item_code' => 'ITM-0088',
            'description' => 'Internal Integration Agreement',
            'serial_number' => 'SN-888-INTEG',
            'sbu_number' => 'SBU-888',
            'region' => 'Visayas',
            'start_date' => '2026-09-01',
            'end_date' => '2027-09-01',
            'confidence_score' => 88.0,
        ];

        // Mock Document Client
        $docClientMock = Mockery::mock(ContractDocumentClient::class);
        $docClientMock->shouldReceive('getDocumentMetadata')
            ->once()
            ->with('123')
            ->andReturn([
                'document_id' => '123',
                'file_name' => 'smart-contract.pdf',
                'file_type' => 'pdf'
            ]);
        $docClientMock->shouldReceive('fetchFileBytes')
            ->once()
            ->with('123')
            ->andReturn('dummy raw bytes of file');
        $this->app->instance(ContractDocumentClient::class, $docClientMock);

        // Mock Text Extractor
        $extractorMock = Mockery::mock(OcrTextExtractor::class);
        $extractorMock->shouldReceive('extract')
            ->once()
            ->andReturn('This is a mock contract text for Smart Communications.');
        $this->app->instance(OcrTextExtractor::class, $extractorMock);

        // Mock Gemini Client
        $geminiMock = Mockery::mock(GeminiClient::class);
        $geminiMock->shouldReceive('generateJson')
            ->once()
            ->andReturn($dummyData);
        $this->app->instance(GeminiClient::class, $geminiMock);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/ocr/extract', [
                'document_id' => '123'
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['business_partner' => 'Smart Communications']);

        $this->assertDatabaseHas('ocr_extractions', [
            'document_id' => 123,
            'status' => 'completed',
            'confidence_score' => 88.0,
        ]);
    }
}
