<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractApprovalStatus;
use App\Models\ContractCategory;
use App\Models\ContractRegion;
use App\Models\ContractStatus;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    public function test_prs_products_sync_maps_sbu_tinyint_and_generates_item_code_when_blank(): void
    {
        config(['services.prs.inbound_key' => 'test-prs-key']);

        $this->withToken('test-prs-key')
            ->postJson('/api/integrations/v1/prs/products/sync', [
                'products' => [
                    [
                        'id'            => 501,
                        'product_name'  => 'Sysmex XN-1000',
                        'product_code'  => 'PRD-501',
                        'serial_number' => 'SN-PRS-501',
                        'sbu'           => 2,
                        'category'      => 'Hematology',
                        'manufacturer'  => 'Sysmex',
                        'brand'         => 'Sysmex',
                        'model'         => 'XN-1000',
                        'is_active'     => true,
                        'description'   => 'Automated analyzer',
                    ],
                    [
                        'id'            => 502,
                        'product_name'  => 'Mindray BC-5000',
                        'serial_number' => 'SN-PRS-502',
                        'sbu'           => 3,
                        'brand'         => 'Mindray',
                        'model'         => 'BC-5000',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('data.0.sbu', 2)
            ->assertJsonPath('data.0.sbu_number', 'SBU-002')
            ->assertJsonPath('data.1.item_code', 'ITM-0001')
            ->assertJsonPath('data.1.sbu', 3);

        $this->withHeaders(['X-Integration-Secret' => 'test-prs-key'])
            ->getJson('/api/integrations/v1/prs/products?sbu=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_code', 'PRD-501')
            ->assertJsonPath('data.0.serial_number', 'SN-PRS-501');
    }

    public function test_prs_closing_status_sync_updates_linked_contract_and_prevents_duplicates(): void
    {
        config(['services.prs.inbound_key' => 'test-prs-key']);
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\SyncContractToMeilisearch::class]);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $category = ContractCategory::firstOrCreate(['category_name' => 'Equipment Lease']);
        $pending = ContractApprovalStatus::firstOrCreate(['status_name' => 'Pending']);
        ContractApprovalStatus::firstOrCreate(['status_name' => 'Approved']);
        ContractApprovalStatus::firstOrCreate(['status_name' => 'Rejected']);
        ContractStatus::firstOrCreate(['status_name' => 'SBSI Review']);
        $region = ContractRegion::firstOrCreate(['region_name' => 'Luzon']);

        $contract = Contract::create([
            'category_id'        => $category->category_id,
            'approval_status_id' => $pending->approval_status_id,
            'region_id'          => $region->region_id,
            'prs_activity_id'    => 9001,
            'bp_name'            => 'St. Luke Medical Center',
            'item_code'          => 'ITM-9001',
            'description'        => 'Analyzer lease',
            'serial_number'      => 'SN-2026-9001',
            'sbu_number'         => 'SBU-001',
            'start_date'         => now()->toDateString(),
            'end_date'           => now()->addYear()->toDateString(),
            'created_by'         => 1,
        ]);

        $eventId = (string) Str::uuid();
        $payload = [
            'event_id'        => $eventId,
            'prs_activity_id' => 9001,
            'revision'        => 1,
            'prs_status'      => 'Won',
        ];

        $this->withToken('test-prs-key')
            ->postJson('/api/integrations/v1/prs/contract-status', $payload)
            ->assertCreated()
            ->assertJsonPath('data.mapped_approval_status', 'Approved')
            ->assertJsonPath('data.mapped_workflow_status', 'SBSI Review')
            ->assertJsonPath('data.contract_id', $contract->contract_id);

        // Exact replay returns 200 and does not duplicate
        $this->withToken('test-prs-key')
            ->postJson('/api/integrations/v1/prs/contract-status', $payload)
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $contract->refresh()->load(['approvalStatus', 'workflowStatus']);
        $this->assertSame('Approved', $contract->approvalStatus?->status_name);
        $this->assertSame('SBSI Review', $contract->workflowStatus?->status_name);
    }

    public function test_prs_closing_handoff_creates_draft_contract_with_existing_category_and_can_submit(): void
    {
        config(['services.prs.inbound_key' => 'test-prs-key']);
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\SyncContractToMeilisearch::class]);
        Http::fake([
            '*/internal/verify-token' => Http::response([
                'valid' => true,
                'user'  => [
                    'id'          => 1,
                    'role'        => 'Sales',
                    'permissions' => ['cms.contracts.create', 'cms.contracts.edit', 'cms.contracts.view'],
                ],
            ], 200),
            '*' => Http::response(['ok' => true], 200),
        ]);

        ContractRegion::firstOrCreate(['region_name' => 'Luzon']);
        ContractCategory::firstOrCreate(['category_name' => 'Equipment Lease']);

        $res = $this->withToken('test-prs-key')
            ->postJson('/api/integrations/v1/prs/closing-outcomes', [
                'event_id'        => (string) Str::uuid(),
                'prs_activity_id' => 9055,
                'revision'        => 1,
                'prs_status'      => 'Closing Completed',
                'category'        => 'Nonexistent PRS Custom Type',
                'institution'     => [
                    'institution_name'   => 'Makati Medical Center',
                    'prs_institution_id' => 12,
                ],
                'product'         => [
                    'product_name'  => 'Roche Cobas c311',
                    'product_code'  => 'PRD-311',
                    'serial_number' => 'SN-311-01',
                    'sbu'           => 4,
                    'brand'         => 'Roche',
                    'model'         => 'c311',
                ],
            ]);

        $res->assertCreated()
            ->assertJsonPath('data.created_contract', true)
            ->assertJsonPath('data.mapped_approval_status', 'Draft');

        $this->assertDatabaseHas('contracts', [
            'prs_activity_id' => 9055,
            'source_system'   => 'PRS',
            'item_code'       => 'PRD-311',
            'sbu_number'      => 'SBU-004',
        ]);
        $this->assertDatabaseMissing('contract_categories', [
            'category_name' => 'Nonexistent PRS Custom Type',
        ]);

        $contractId = $res->json('data.contract_id');
        $this->withToken('test-user-jwt')
            ->postJson("/api/contracts/{$contractId}/submit-draft")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['document_ids']);

        $doc = \App\Models\Document::create([
            'contract_id' => null,
            'uploaded_by' => 1,
            'file_name'   => 'generated-draft.pdf',
            'file_path'   => 'contracts/documents/generated-draft.pdf',
            'file_type'   => 'pdf',
            'file_size'   => 1024,
        ]);

        $this->withToken('test-user-jwt')
            ->postJson("/api/contracts/{$contractId}/draft-document", [
                'document_id' => (string) $doc->getKey(),
            ])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'Draft')
            ->assertJsonPath('data.documents.0.document_id', (string) $doc->getKey());

        $this->withToken('test-user-jwt')
            ->postJson("/api/contracts/{$contractId}/submit-draft")
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'Pending')
            ->assertJsonPath('data.creator_name', 'PRS Employee');
    }

    public function test_prs_sync_and_webhook_degrade_gracefully_when_tunnel_expired(): void
    {
        config([
            'services.prs.products_url' => 'https://expired-tunnel.trycloudflare.com/api/integrations/cms/products',
            'services.prs.webhook_url'  => 'https://expired-tunnel.trycloudflare.com/api/integrations/cms/contract-outcome',
            'services.prs.outbound_key' => 'test-prs-key',
        ]);

        Product::create([
            'prs_product_id' => 777,
            'item_code'      => 'ITM-0777',
            'serial_number'  => 'SN-2026-0777',
            'product_name'   => 'Existing Local Product',
            'sbu_number'     => 'SBU-001',
            'sbu'            => 1,
            'category'       => 'Equipment',
            'description'    => 'Retained when PRS offline',
            'is_active'      => true,
        ]);

        Http::fake([
            'https://expired-tunnel.trycloudflare.com/*' => Http::response('error code: 1033', 530),
        ]);

        $this->artisan('prs:sync-products')->assertExitCode(0);
        $this->assertDatabaseHas('products', ['item_code' => 'ITM-0777', 'is_active' => true]);

        // Job should log warning and return without throwing when tunnel returns 530 or connection failure.
        (new \App\Jobs\NotifyPrsOfContractUpdateJob(99, 1, 'Approved', 'Won'))->handle();
        Http::assertSentCount(2);
    }
}
