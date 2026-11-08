<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use Mockery;
use Tests\TestCase;

/**
 * Feature 4 (Analytics): covers the internal AI metrics snapshot endpoint
 * used by analytics-service's descriptive/diagnostic aggregation.
 */
final class InternalMetricsEndpointTest extends TestCase
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

    private function secretHeader(): array
    {
        return ['X-Internal-Secret' => env('INTERNAL_SERVICE_SECRET', '')];
    }

    public function test_request_without_secret_is_rejected(): void
    {
        $response = $this->getJson('/api/internal/metrics/ai');
        $response->assertStatus(401);
    }

    public function test_returns_ai_metrics_snapshot(): void
    {
        DB::table('risk_assessment_results')->insert([
            'contract_id' => 1, 'risk_score' => 80, 'risk_level' => 'high',
            'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeaders($this->secretHeader())->getJson('/api/internal/metrics/ai');

        $response->assertOk();
        $response->assertJsonPath('contracts_scanned', 1);
        $response->assertJsonPath('scans_completed', 1);
        $response->assertJsonStructure([
            'contracts_scanned', 'scans_completed', 'scans_failed', 'by_risk_level',
            'avg_risk_score', 'most_cited_playbook_clauses', 'vendor_suggestions_total',
            'vendor_suggestions_accepted', 'vendor_suggestions_dismissed',
        ]);
    }
}
