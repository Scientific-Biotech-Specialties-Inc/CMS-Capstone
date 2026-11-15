<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_codes_can_be_assigned_or_generated(): void
    {
        $path = '/api/integrations/v1/products';
        $details = [
            'product_name' => 'Router',
            'sbu_number' => 'SBU-001',
            'description' => 'Network router',
            'category' => 'Networking',
        ];

        $this->withoutMiddleware();
        $this->postJson($path, [...$details, 'item_code' => 'ITM-0001'])
            ->assertCreated()->assertJsonPath('data.item_code', 'ITM-0001');
        $this->postJson($path, $details)->assertCreated()->assertJsonPath('data.item_code', 'ITM-0002');
        $this->postJson($path, [...$details, 'item_code' => 'ITM-0001'])->assertUnprocessable();
    }

    public function test_catalog_accepts_a_private_peer_without_a_user_token(): void
    {
        config(['services.prs.catalog_networks' => '10.80.1.7/32']);
        Product::create([
            'item_code' => 'ITM-0041',
            'product_name' => 'Router',
            'sbu_number' => 'SBU-001',
            'description' => 'Network router',
            'category' => 'Networking',
            'manufacturer' => 'Example',
            'model' => 'R1',
        ]);

        $path = '/api/integrations/v1/products';
        $this->getJson($path, ['X-Forwarded-For' => '10.80.1.7'])->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '10.80.1.7'])->getJson($path)
            ->assertOk()
            ->assertJsonPath('data.0.item_code', 'ITM-0041')
            ->assertJsonPath('data.0.manufacturer', 'Example')
            ->assertJsonPath('data.0.model', 'R1')
            ->assertJsonMissingPath('data.0.created_at');
    }

    public function test_public_catalog_access_requires_an_explicit_opt_in(): void
    {
        $path = '/api/integrations/v1/products';
        config(['services.prs.catalog_public_read' => false, 'services.prs.catalog_networks' => '']);
        $this->getJson($path)->assertForbidden();

        config(['services.prs.catalog_public_read' => true]);
        $this->getJson($path)->assertOk()->assertJsonPath('data', []);
        $this->postJson('/api/integrations/v1/prs/closing-outcomes', [])->assertUnauthorized();
    }

    public function test_closing_events_are_authenticated_and_replay_safe(): void
    {
        config(['services.prs.inbound_key' => 'test-prs-key']);
        $path = '/api/integrations/v1/prs/closing-outcomes';
        $event = ['event_id' => (string) Str::uuid(), 'prs_activity_id' => 42, 'revision' => 2, 'outcome' => 'Won'];

        $this->postJson($path, $event)->assertUnauthorized();
        $this->withToken('test-prs-key')->postJson($path, $event)->assertCreated();
        $this->postJson($path, $event)->assertOk();
        $this->postJson($path, [...$event, 'outcome' => 'Loss'])->assertConflict();
        $this->postJson($path, [...$event, 'event_id' => (string) Str::uuid(), 'revision' => 1, 'outcome' => 'Follow-up'])
            ->assertCreated();

        $this->assertDatabaseCount('prs_closing_events', 2);
        $this->assertSame('Won', DB::table('prs_closing_events')->where('prs_activity_id', 42)->orderByDesc('revision')->value('outcome'));
    }
}
