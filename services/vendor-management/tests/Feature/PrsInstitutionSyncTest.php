<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BusinessPartner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PrsInstitutionSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $prsKey = 'prs-test-secret-2026';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.prs.inbound_key' => $this->prsKey,
            'services.prs.internal_secret' => 'internal-test-secret',
        ]);
    }

    public function test_business_partner_requires_address_and_valid_class_tier(): void
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user'  => [
                    'id'          => 10,
                    'role'        => 'Admin',
                    'permissions' => ['cms.partners.view', 'cms.partners.create', 'cms.partners.edit'],
                    'department'  => 'Sales & Marketing',
                ],
            ]),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer mock-token'])
            ->postJson('/api/partners', [
                'bp_code'      => 'BP-REQ-1',
                'partner_name' => 'Missing Fields Hospital',
                'email'        => 'missing@hospital.test',
                'class_tier'   => 'Class Z',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address', 'class_tier']);

        // Shorthand "A" normalizes to "Class A"
        $this->withHeaders(['Authorization' => 'Bearer mock-token'])
            ->postJson('/api/partners', [
                'bp_code'      => 'BP-REQ-2',
                'partner_name' => 'Normalized Tier Hospital',
                'email'        => 'norm@hospital.test',
                'address'      => '123 Ayala Ave, Makati City',
                'region'       => 'Luzon',
                'class_tier'   => 'A',
            ])
            ->assertCreated()
            ->assertJsonPath('data.class_tier', 'Class A');
    }

    public function test_prs_institutions_and_locations_endpoints_expose_class_tier_and_branches(): void
    {
        $this->getJson('/api/integrations/v1/prs/institutions')->assertStatus(403);

        $partner = BusinessPartner::create([
            'bp_code'      => 'BP-2001',
            'partner_name' => 'Metro Medical Center',
            'email'        => 'metro@med.test',
            'address'      => '500 Ortigas Ave, Pasig City',
            'region'       => 'Luzon',
            'class_tier'   => 'Class B',
            'status'       => 'Active',
        ]);

        $syncRes = $this->withHeaders(['Authorization' => 'Bearer ' . $this->prsKey])
            ->postJson('/api/integrations/v1/prs/institutions/sync', [
                'institution_uid'    => 'BP-2001',
                'prs_institution_id' => 77,
                'institution_name'   => 'Metro Medical Center',
                'address'            => '500 Ortigas Ave, Pasig City',
                'class_tier'         => 'Class B',
                'branches'           => [
                    [
                        'prs_branch_id' => 701,
                        'branch_name'   => 'Ortigas Annex',
                        'address'       => '502 Ortigas Ave, Pasig City',
                        'region'        => 'Luzon',
                        'sbsi_branch'   => 'Main Office',
                    ],
                ],
            ]);

        $syncRes->assertOk()
            ->assertJsonPath('data.prs_institution_id', 77)
            ->assertJsonPath('data.class_tier', 'Class B');

        $listRes = $this->withHeaders(['X-Integration-Secret' => $this->prsKey])
            ->getJson('/api/integrations/v1/prs/institutions?class_tier=Class%20B');

        $listRes->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.institution_uid', 'BP-2001')
            ->assertJsonPath('data.0.address', '500 Ortigas Ave, Pasig City')
            ->assertJsonPath('data.0.class_tier', 'Class B')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonMissingPath('data.0.latitude')
            ->assertJsonMissingPath('data.0.longitude');

        $locRes = $this->withHeaders(['X-Integration-Secret' => $this->prsKey])
            ->getJson('/api/integrations/v1/prs/locations?institution_uid=BP-2001');

        $locRes->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.branch_name', 'Ortigas Annex');
    }
}
