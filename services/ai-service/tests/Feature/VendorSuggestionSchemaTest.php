<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;
use Mockery;
use Tests\TestCase;

/**
 * Verifies Feature 3's schema additions: vendor_suggestions gets
 * requested_by/industry_hint/region_hint, and the new
 * vendor_suggestion_candidates table migrates correctly with its FK.
 */
final class VendorSuggestionSchemaTest extends TestCase
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

    public function test_vendor_suggestions_gains_hint_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('vendor_suggestions', [
            'requested_by', 'industry_hint', 'region_hint',
        ]));
    }

    public function test_vendor_suggestion_candidates_table_migrates(): void
    {
        $this->assertTrue(Schema::hasTable('vendor_suggestion_candidates'));
        $this->assertTrue(Schema::hasColumns('vendor_suggestion_candidates', [
            'id', 'vendor_suggestion_id', 'candidate_name', 'candidate_industry',
            'candidate_region', 'candidate_contact_email', 'candidate_contact_number',
            'candidate_address', 'suggestion_score', 'suggestion_reason',
            'gemini_raw_response', 'decision', 'decided_by', 'decided_at',
            'created_at', 'updated_at',
        ]));
    }

    public function test_candidate_cascade_deletes_with_its_suggestion_batch(): void
    {
        $suggestionId = DB::table('vendor_suggestions')->insertGetId([
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('vendor_suggestion_candidates')->insert([
            'vendor_suggestion_id' => $suggestionId,
            'candidate_name'       => 'Test Co.',
            'decision'             => 'pending',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $this->assertDatabaseCount('vendor_suggestion_candidates', 1);

        DB::table('vendor_suggestions')->where('id', $suggestionId)->delete();

        $this->assertDatabaseCount('vendor_suggestion_candidates', 0);
    }
}
