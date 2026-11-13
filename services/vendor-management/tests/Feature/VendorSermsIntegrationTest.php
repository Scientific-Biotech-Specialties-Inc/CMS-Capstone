<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SuggestedVendor;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorSermsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function secretHeader(): array
    {
        return ['X-Internal-Secret' => env('INTERNAL_SERVICE_SECRET', '')];
    }

    public function test_lookup_matches_accredited_supplier_by_tin(): void
    {
        Supplier::create([
            'supplier_name'            => 'SBSI Lab Supplies Inc.',
            'tin_number'               => '123-456-789-000',
            'status'                   => 'Active',
            'is_accredited'            => true,
            'is_flagged'               => false,
            'default_expense_category' => 'Spare Parts',
        ]);

        $response = $this->withHeaders($this->secretHeader())
            ->getJson('/api/internal/vendors/lookup?tin=123456789000');

        $response->assertOk()
            ->assertJsonPath('data.matched', true)
            ->assertJsonPath('data.match_type', 'tin')
            ->assertJsonPath('data.is_accredited', true)
            ->assertJsonPath('data.requires_audit', false)
            ->assertJsonPath('data.default_expense_category', 'Spare Parts');
    }

    public function test_lookup_flags_unlisted_vendor_for_audit(): void
    {
        $response = $this->withHeaders($this->secretHeader())
            ->getJson('/api/internal/vendors/lookup?tin=999-999-999-000&name=Unknown+Shop');

        $response->assertOk()
            ->assertJsonPath('data.matched', false)
            ->assertJsonPath('data.requires_audit', true);
    }

    public function test_serms_can_submit_suggested_vendor(): void
    {
        $response = $this->withHeaders($this->secretHeader())
            ->postJson('/api/internal/vendors/suggest', [
                'vendor_name'              => 'New Field Service Co.',
                'tin_number'               => '555-666-777-000',
                'default_expense_category' => 'Field Service',
                'source_receipt_id'        => 'RCP-9001',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'Pending')
            ->assertJsonPath('data.source_system', 'SERMS');

        $this->assertDatabaseHas('suggested_vendors', [
            'vendor_name' => 'New Field Service Co.',
            'tin_number'  => '555-666-777-000',
        ]);
    }
}
