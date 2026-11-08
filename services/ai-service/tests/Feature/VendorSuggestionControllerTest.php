<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Fluent;
use Mockery;
use Tests\TestCase;

/**
 * Feature 3: covers the Admin-only gate, the accept/dismiss decision flow
 * (accept creates a real vendor via vendor-management, dismiss doesn't),
 * and that a second decision on an already-decided candidate is rejected.
 */
final class VendorSuggestionControllerTest extends TestCase
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

    public function test_non_admin_cannot_request_suggestions(): void
    {
        $this->fakeAuth('Manager');
        Bus::fake();

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/vendor-suggestions', ['industry_hint' => 'diagnostics']);

        $response->assertStatus(403);
    }

    public function test_admin_can_request_suggestions_and_job_is_queued(): void
    {
        $this->fakeAuth('Admin');
        Bus::fake();

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/vendor-suggestions', ['industry_hint' => 'diagnostics', 'region_hint' => 'Luzon']);

        $response->assertStatus(202);
        $this->assertDatabaseHas('vendor_suggestions', [
            'industry_hint' => 'diagnostics',
            'region_hint'   => 'Luzon',
            'requested_by'  => 1,
            'status'        => 'pending',
        ]);
        Bus::assertDispatched(\App\Jobs\RunVendorSuggestionPipeline::class);
    }

    public function test_accepting_a_candidate_creates_a_real_vendor_via_vendor_management(): void
    {
        $this->fakeAuth('Admin');
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user'  => ['id' => 1, 'role' => 'Admin', 'permissions' => []],
            ], 200),
            'http://vendor-management:8000/api/internal/suppliers' => Http::response([
                'data' => ['supplier_id' => 77],
            ], 201),
        ]);

        $suggestionId = DB::table('vendor_suggestions')->insertGetId([
            'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $candidateId = DB::table('vendor_suggestion_candidates')->insertGetId([
            'vendor_suggestion_id' => $suggestionId,
            'candidate_name'       => 'PH Diagnostics Co.',
            'decision'             => 'pending',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->patchJson("/api/vendor-suggestion-candidates/{$candidateId}", [
                'decision'    => 'accepted',
                'vendor_type' => 'supplier',
                'fields'      => [
                    'name'     => 'PH Diagnostics Co.',
                    'industry' => 'Diagnostics',
                    'region'   => 'Luzon',
                    'email'    => 'contact@phdiagnostics.ph',
                ],
            ]);

        $response->assertOk();
        $response->assertJson(['vendor_id' => 77]);
        $this->assertDatabaseHas('vendor_suggestion_candidates', [
            'id'       => $candidateId,
            'decision' => 'accepted',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/internal/suppliers')
                && $request->method() === 'POST'
                && $request['supplier_name'] === 'PH Diagnostics Co.';
        });
    }

    public function test_dismissing_a_candidate_does_not_call_vendor_management(): void
    {
        $this->fakeAuth('Admin');

        $suggestionId = DB::table('vendor_suggestions')->insertGetId([
            'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $candidateId = DB::table('vendor_suggestion_candidates')->insertGetId([
            'vendor_suggestion_id' => $suggestionId,
            'candidate_name'       => 'Irrelevant Co.',
            'decision'             => 'pending',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->patchJson("/api/vendor-suggestion-candidates/{$candidateId}", [
                'decision' => 'dismissed',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('vendor_suggestion_candidates', [
            'id'       => $candidateId,
            'decision' => 'dismissed',
        ]);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'vendor-management'));
    }

    public function test_deciding_an_already_decided_candidate_is_rejected(): void
    {
        $this->fakeAuth('Admin');

        $suggestionId = DB::table('vendor_suggestions')->insertGetId([
            'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $candidateId = DB::table('vendor_suggestion_candidates')->insertGetId([
            'vendor_suggestion_id' => $suggestionId,
            'candidate_name'       => 'Already Decided Co.',
            'decision'             => 'dismissed',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->patchJson("/api/vendor-suggestion-candidates/{$candidateId}", [
                'decision' => 'dismissed',
            ]);

        $response->assertStatus(422);
    }
}
