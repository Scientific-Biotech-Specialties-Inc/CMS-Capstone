<?php

namespace Tests\Feature;

use Database\Seeders\PlaybookClauseSeeder;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;
use Mockery;
use Tests\TestCase;

/**
 * Verifies the new playbook_clauses / risk_assessment_findings tables
 * (Feature 1) migrate correctly and the seeder populates the drafted
 * clause library.
 */
final class PlaybookClauseSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
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

    public function test_playbook_clauses_and_findings_tables_migrate(): void
    {
        $this->assertTrue(Schema::hasTable('playbook_clauses'));
        $this->assertTrue(Schema::hasColumns('playbook_clauses', [
            'id', 'clause_code', 'title', 'standard_text', 'category', 'is_active', 'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasTable('risk_assessment_findings'));
        $this->assertTrue(Schema::hasColumns('risk_assessment_findings', [
            'id', 'risk_assessment_result_id', 'clause_reference', 'severity',
            'playbook_clause_id', 'retrieval_score', 'deviation_reason',
            'recommended_remediation', 'created_at',
        ]));
    }

    public function test_seeder_populates_the_drafted_clause_library(): void
    {
        $this->seed(PlaybookClauseSeeder::class);

        $this->assertDatabaseCount('playbook_clauses', 14);
        $this->assertDatabaseHas('playbook_clauses', ['clause_code' => 'PAY-01', 'category' => 'Payment Terms']);
        $this->assertDatabaseHas('playbook_clauses', ['clause_code' => 'DATA-01', 'category' => 'Data Handling & Confidentiality']);

        // Re-running the seeder is idempotent (updateOrCreate keyed on clause_code).
        $this->seed(PlaybookClauseSeeder::class);
        $this->assertDatabaseCount('playbook_clauses', 14);
    }

    public function test_finding_cascade_deletes_with_its_result_and_nulls_on_clause_delete(): void
    {
        $resultId = DB::table('risk_assessment_results')->insertGetId([
            'contract_id' => 1,
            'status'      => 'completed',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $clauseId = DB::table('playbook_clauses')->insertGetId([
            'clause_code'   => 'TEST-01',
            'title'         => 'Test Clause',
            'standard_text' => 'Test.',
            'category'      => 'Test',
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::table('risk_assessment_findings')->insert([
            'risk_assessment_result_id' => $resultId,
            'clause_reference'          => 'Some excerpt',
            'severity'                  => 'high',
            'playbook_clause_id'        => $clauseId,
            'deviation_reason'          => 'Reason',
            'recommended_remediation'   => 'Remediation',
            'created_at'                => now(),
        ]);

        DB::table('playbook_clauses')->where('id', $clauseId)->delete();
        $this->assertNull(DB::table('risk_assessment_findings')->first()->playbook_clause_id);

        DB::table('risk_assessment_results')->where('id', $resultId)->delete();
        $this->assertDatabaseCount('risk_assessment_findings', 0);
    }
}
