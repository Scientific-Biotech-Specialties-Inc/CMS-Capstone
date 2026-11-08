<?php

namespace Tests\Feature;

use App\Services\Gemini\GeminiClient;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Fluent;
use Mockery;
use Tests\TestCase;

final class ContractGenerationControllerTest extends TestCase
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

    private function fakeAuth(string $role = 'Sales', int $id = 1): void
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user'  => ['id' => $id, 'role' => $role, 'permissions' => []],
            ], 200),
        ]);
    }

    public function test_rejects_unauthenticated_request(): void
    {
        $response = $this->postJson('/api/contracts/generate', []);
        $response->assertStatus(401);
    }

    public function test_validates_required_fields(): void
    {
        $this->fakeAuth();

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->postJson('/api/contracts/generate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'business_partner',
                'category',
                'item_code',
                'description',
                'serial_number',
                'sbu_number',
                'region',
                'start_date',
                'end_date',
            ]);
    }

    public function test_generates_contract_using_fallback_when_gemini_returns_null(): void
    {
        $this->fakeAuth();

        $geminiMock = Mockery::mock(GeminiClient::class);
        $geminiMock->shouldReceive('generateJson')
            ->once()
            ->andReturn(null);

        $this->app->instance(GeminiClient::class, $geminiMock);

        $payload = [
            'business_partner' => 'Acme Technologies',
            'category'         => 'Service Agreement',
            'item_code'        => 'ITM-0041',
            'description'      => 'Core Network Maintenance',
            'serial_number'    => 'SN-2026-0041',
            'sbu_number'       => 'SBU-001',
            'region'           => 'Luzon',
            'start_date'       => '2026-01-01',
            'end_date'         => '2026-12-31',
        ];

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->postJson('/api/contracts/generate', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'source' => 'template_engine',
            ])
            ->assertJsonPath('data.title', 'COMMERCIAL SERVICES AGREEMENT - Acme Technologies');

        $content = $response->json('data.html_content');
        $this->assertStringContainsString('Acme Technologies', $content);
        $this->assertStringContainsString('ITM-0041', $content);
        $this->assertStringContainsString('SN-2026-0041', $content);
        $this->assertStringContainsString('SERVICE LEVEL AGREEMENT (SLA)', $content);
    }

    public function test_generates_category_specific_contracts_with_tailored_blueprints(): void
    {
        $this->fakeAuth();

        $categories = [
            'Supply Contract'       => ['COMMERCIAL SUPPLY CONTRACT', 'PURCHASER', 'INSPECTION, ACCEPTANCE'],
            'Equipment Lease'       => ['EQUIPMENT LEASE AGREEMENT', 'LESSEE', 'RENTAL CHARGES, SECURITY DEPOSIT'],
            'Equipment Maintenance' => ['EQUIPMENT MAINTENANCE & SERVICING AGREEMENT', 'MAINTENANCE PROVIDER', 'PREVENTIVE MAINTENANCE SCHEDULE'],
            'Partnership Agreement' => ['STRATEGIC PARTNERSHIP & COLLABORATION AGREEMENT', 'FIRST PARTNER', 'JOINT GOVERNANCE'],
        ];

        $geminiMock = Mockery::mock(GeminiClient::class);
        $geminiMock->shouldReceive('generateJson')->andReturn(null);
        $this->app->instance(GeminiClient::class, $geminiMock);

        foreach ($categories as $category => [$expectedTitlePrefix, $expectedRole, $expectedClause]) {

            $payload = [
                'business_partner' => 'Partner Corp',
                'category'         => $category,
                'item_code'        => 'CODE-100',
                'description'      => 'Industrial Machinery Item',
                'serial_number'    => 'SER-999',
                'sbu_number'       => 'SBU-002',
                'region'           => 'Visayas',
                'start_date'       => '2026-02-01',
                'end_date'         => '2027-02-01',
            ];

            $response = $this->withHeader('Authorization', 'Bearer valid-token')
                ->postJson('/api/contracts/generate', $payload);

            $response->assertStatus(200);
            $this->assertStringStartsWith($expectedTitlePrefix, $response->json('data.title'));

            $html = $response->json('data.html_content');
            $this->assertStringContainsString($expectedRole, $html);
            $this->assertStringContainsString($expectedClause, $html);
            $this->assertStringContainsString('crms-page-break', $html);
            $this->assertStringContainsString('crms-quill-table', $html);
        }
    }
}
