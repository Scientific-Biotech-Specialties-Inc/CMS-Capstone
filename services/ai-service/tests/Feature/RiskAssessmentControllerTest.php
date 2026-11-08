<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;
use Mockery;
use Tests\TestCase;

/**
 * US-026: covers the role matrix (Manager: decision; Sales: view own only)
 * and the summary/PDF endpoints against a seeded risk_assessment_results +
 * risk_assessment_findings row, without making any real Gemini API calls.
 */
final class RiskAssessmentControllerTest extends TestCase
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

    private function fakeAuth(array $user): void
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user'  => $user,
            ], 200),
        ]);
    }

    private function seedResultWithFinding(int $contractId): int
    {
        $clauseId = DB::table('playbook_clauses')->insertGetId([
            'clause_code' => 'PAY-01', 'title' => 'Payment Terms', 'standard_text' => 'Net 30.',
            'category' => 'Payment Terms', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $resultId = DB::table('risk_assessment_results')->insertGetId([
            'contract_id' => $contractId, 'risk_score' => 70, 'risk_level' => 'high',
            'findings' => json_encode(['count' => 1]), 'status' => 'completed', 'scanned_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('risk_assessment_findings')->insert([
            'risk_assessment_result_id' => $resultId,
            'clause_reference' => 'Payment due within 90 days',
            'severity' => 'high',
            'playbook_clause_id' => $clauseId,
            'retrieval_score' => 0.91,
            'deviation_reason' => 'Extends payment window beyond the standard 30 days.',
            'recommended_remediation' => 'Negotiate payment terms down to 30-45 days.',
            'created_at' => now(),
        ]);

        return $resultId;
    }

    public function test_manager_can_view_any_contracts_summary(): void
    {
        $this->fakeAuth(['id' => 99, 'role' => 'Manager', 'permissions' => []]);
        $this->seedResultWithFinding(contractId: 55);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->getJson('/api/contracts/55/risk-assessment/summary');

        $response->assertOk();
        $response->assertJsonPath('data.risk_level', 'high');
        $response->assertJsonPath('data.findings.0.playbook_clause_code', 'PAY-01');
        $response->assertJsonPath('data.findings.0.severity', 'high');
    }

    public function test_sales_can_view_own_contracts_summary(): void
    {
        $this->fakeAuth(['id' => 42, 'role' => 'Sales', 'permissions' => []]);
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response(['valid' => true, 'user' => ['id' => 42, 'role' => 'Sales']], 200),
            'http://contract-management:8000/api/internal/contracts/55/owner' => Http::response(['created_by' => 42], 200),
        ]);
        $this->seedResultWithFinding(contractId: 55);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->getJson('/api/contracts/55/risk-assessment/summary');

        $response->assertOk();
    }

    public function test_sales_cannot_view_another_users_contract_summary(): void
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response(['valid' => true, 'user' => ['id' => 42, 'role' => 'Sales']], 200),
            'http://contract-management:8000/api/internal/contracts/55/owner' => Http::response(['created_by' => 999], 200),
        ]);
        $this->seedResultWithFinding(contractId: 55);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->getJson('/api/contracts/55/risk-assessment/summary');

        $response->assertStatus(403);
    }

    public function test_summary_returns_404_when_no_assessment_exists(): void
    {
        $this->fakeAuth(['id' => 99, 'role' => 'Manager', 'permissions' => []]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->getJson('/api/contracts/999/risk-assessment/summary');

        $response->assertStatus(404);
    }

    public function test_scan_endpoint_queues_the_pipeline_job_without_running_it_inline(): void
    {
        Bus::fake();
        $this->fakeAuth(['id' => 99, 'role' => 'Manager', 'permissions' => []]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts/55/risk-assessment/scan');

        $response->assertStatus(202);
        Bus::assertDispatched(\App\Jobs\RunRiskAssessmentPipeline::class, fn ($job) => $job->contractId === 55);
    }

    public function test_pdf_export_returns_a_pdf_response(): void
    {
        $this->fakeAuth(['id' => 99, 'role' => 'Manager', 'permissions' => []]);
        $this->seedResultWithFinding(contractId: 55);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->get('/api/contracts/55/risk-assessment/summary/pdf');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/contracts/55/risk-assessment/summary');
        $response->assertStatus(401);
    }
}
