<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;
use Mockery;
use Tests\TestCase;

final class AiServiceSchemaTest extends TestCase
{
    /**
     * The SQLite grammar used by this test accepts the pgvector declaration
     * while the extension statement is mocked below. Production migrations
     * still run against PostgreSQL/pgvector in the service container.
     */
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

        $this->artisan('migrate:fresh', [
            '--database' => 'sqlite',
            '--force' => true,
        ])->assertExitCode(0);
    }

    public function test_all_ai_candidate_tables_and_columns_are_migrated(): void
    {
        $expectedColumns = [
            'risk_assessment_results' => [
                'id', 'document_id', 'contract_id', 'risk_score', 'risk_level',
                'findings', 'status', 'scanned_at', 'created_at', 'updated_at',
            ],
            'vendor_suggestions' => [
                'id', 'vendor_type', 'vendor_id', 'contract_id', 'suggestion_score',
                'suggestion_reason', 'status', 'suggested_at', 'created_at', 'updated_at',
            ],
            'ocr_extractions' => [
                'id', 'document_id', 'contract_id', 'extracted_fields',
                'confidence_score', 'status', 'extracted_at', 'created_at', 'updated_at',
            ],
            'embeddings' => [
                'id', 'entity_type', 'entity_id', 'embedding', 'model_name',
                'created_at', 'updated_at',
            ],
            'audit_logs' => [
                'audit_id', 'action', 'entity_type', 'entity_id', 'user_id',
                'old_data', 'new_data', 'performed_at', 'user_name', 'user_email',
                'user_role', 'user_department',
            ],
        ];

        foreach ($expectedColumns as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} to exist.");
            $this->assertTrue(
                Schema::hasColumns($table, $columns),
                "Expected {$table} to contain all documented columns."
            );
        }

        $embeddingColumn = collect(Schema::getColumns('embeddings'))
            ->firstWhere('name', 'embedding');

        $this->assertNotNull($embeddingColumn);
        $this->assertSame('vector(1536)', strtolower((string) $embeddingColumn['type']));
    }

    public function test_schema_health_reports_only_existence_booleans(): void
    {
        DB::table('risk_assessment_results')->insert([
            'document_id' => 42,
            'contract_id' => 84,
            'risk_score' => 97.5,
            'risk_level' => 'critical',
            'findings' => json_encode(['secret' => 'risk-result-sentinel']),
            'status' => 'completed',
            'scanned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = $this->getJson('/health/schema')
            ->assertOk()
            ->json();

        $this->assertSame(['status', 'schema'], array_keys($payload));
        $this->assertSame('ok', $payload['status']);

        $tables = [
            'risk_assessment_results',
            'vendor_suggestions',
            'ocr_extractions',
            'embeddings',
            'audit_logs',
        ];

        $this->assertSame($tables, array_keys($payload['schema']));

        foreach ($tables as $table) {
            $this->assertSame(
                ['table_exists', 'columns_exist'],
                array_keys($payload['schema'][$table])
            );
            $this->assertIsBool($payload['schema'][$table]['table_exists']);
            $this->assertIsBool($payload['schema'][$table]['columns_exist']);
            $this->assertTrue($payload['schema'][$table]['table_exists']);
            $this->assertTrue($payload['schema'][$table]['columns_exist']);
        }

        $this->assertArrayNotHasKey('data', $payload);
        $this->assertStringNotContainsString('risk-result-sentinel', json_encode($payload));
        $this->assertStringNotContainsString('critical', json_encode($payload));
        $this->assertStringNotContainsString('embedding', json_encode($payload['schema']['embeddings']));
    }
}
