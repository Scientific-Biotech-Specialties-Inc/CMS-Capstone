<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 3 (Vendor AI Suggestions, ai-service): covers the new
 * X-Internal-Secret-gated endpoints used for the embedding backfill
 * (list suppliers/partners) and for creating a vendor from an accepted
 * suggestion candidate.
 */
class InternalVendorEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function secretHeader(): array
    {
        return ['X-Internal-Secret' => env('INTERNAL_SERVICE_SECRET', '')];
    }

    public function test_request_without_secret_is_rejected()
    {
        $response = $this->getJson('/api/internal/suppliers');
        $response->assertStatus(401);
    }

    public function test_request_with_wrong_secret_is_rejected()
    {
        $response = $this->withHeaders(['X-Internal-Secret' => 'wrong-secret'])
            ->getJson('/api/internal/suppliers');
        $response->assertStatus(401);
    }

    public function test_lists_suppliers_for_backfill()
    {
        \Illuminate\Support\Facades\DB::table('suppliers')->insert([
            'supplier_name' => 'Test Supplier Co.',
            'tin_number'    => '123-456-789',
            'industry'      => 'Diagnostics',
            'region'        => 'Luzon',
            'status'        => 'Active',
            'created_at'    => now(),
        ]);

        $response = $this->withHeaders($this->secretHeader())->getJson('/api/internal/suppliers');

        $response->assertOk();
        $response->assertJsonPath('data.0.supplier_name', 'Test Supplier Co.');
    }

    public function test_lists_partners_for_backfill()
    {
        \Illuminate\Support\Facades\DB::table('business_partners')->insert([
            'bp_code'      => 'BP-TEST-1',
            'partner_name' => 'Test Partner Co.',
            'email'        => 'test@partner.ph',
            'region'       => 'Visayas',
            'status'       => 'Active',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->withHeaders($this->secretHeader())->getJson('/api/internal/partners');

        $response->assertOk();
        $response->assertJsonPath('data.0.partner_name', 'Test Partner Co.');
    }

    public function test_creates_a_supplier_from_accepted_candidate()
    {
        $response = $this->withHeaders($this->secretHeader())->postJson('/api/internal/suppliers', [
            'supplier_name'  => 'AI Suggested Supplier',
            'industry'       => 'Diagnostics',
            'region'         => 'Luzon',
            'email'          => 'contact@aisupplier.ph',
            'decided_by'     => 5,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('suppliers', [
            'supplier_name' => 'AI Suggested Supplier',
        ]);
    }

    public function test_creates_a_partner_from_accepted_candidate()
    {
        $response = $this->withHeaders($this->secretHeader())->postJson('/api/internal/partners', [
            'bp_code'      => 'BP-AI-TEST',
            'partner_name' => 'AI Suggested Partner',
            'email'        => 'contact@aipartner.ph',
            'region'       => 'Mindanao',
            'decided_by'   => 5,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('business_partners', [
            'partner_name' => 'AI Suggested Partner',
            'created_by'   => 5,
        ]);
    }

    public function test_duplicate_supplier_tin_is_rejected()
    {
        \Illuminate\Support\Facades\DB::table('suppliers')->insert([
            'supplier_name' => 'Existing Co.',
            'tin_number'    => '999-999-999',
            'status'        => 'Active',
            'created_at'    => now(),
        ]);

        $response = $this->withHeaders($this->secretHeader())->postJson('/api/internal/suppliers', [
            'supplier_name' => 'Different Name Co.',
            'tin_number'    => '999-999-999',
        ]);

        $response->assertStatus(409);
    }
}
