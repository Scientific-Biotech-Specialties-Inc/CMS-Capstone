<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateInternal;
use App\Http\Middleware\HasPermission;
use App\Models\BusinessPartner;
use App\Models\Supplier;
use App\Models\VendorReimbursement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendorReimbursementSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([AuthenticateInternal::class, HasPermission::class]);
        config(['services.serms.url' => 'http://serms-mock/api/serms']);
        config(['services.serms.secret' => 'secret']);
    }

    public function test_syncs_and_persists_reimbursements_for_supplier(): void
    {
        $supplier = Supplier::create([
            'supplier_name' => 'Bayan Telecommunications , Inc',
            'tin_number'    => '000-774-471-025',
            'status'        => 'Active',
        ]);

        Http::fake([
            'http://serms-mock/api/serms/internal/reimbursements/by-vendor*' => Http::response([
                'data' => [
                    [
                        'reimbursement_id' => 8,
                        'reference_no'     => 'RMB-8',
                        'tin_number'       => '000-774-471-025',
                        'vendor_name'      => 'Bayan Telecommunications , Inc',
                        'category'         => 'Others',
                        'description'      => 'Bayan Telecommunications , Inc',
                        'amount'           => 1500.50,
                        'status'           => 'Pending',
                        'employee_name'    => 'Summer Love',
                        'contract_id'      => null,
                        'expense_date'     => '2026-08-09',
                        'created_at'       => '2026-11-11T05:43:49Z',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson("/api/suppliers/{$supplier->supplier_id}/reimbursements");

        $response->assertOk()
            ->assertJsonPath('meta.serms_connected', true)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.total_amount', 1500.5)
            ->assertJsonPath('data.0.reference_no', 'RMB-8')
            ->assertJsonPath('data.0.employee_name', 'Summer Love');

        $this->assertDatabaseHas('vendor_reimbursements', [
            'vendor_type'            => 'supplier',
            'vendor_id'              => $supplier->supplier_id,
            'serms_reimbursement_id' => 8,
            'reference_no'           => 'RMB-8',
        ]);
    }

    public function test_falls_back_to_stored_reimbursements_when_serms_offline(): void
    {
        $partner = BusinessPartner::create([
            'bp_code'      => 'BP-0042',
            'partner_name' => 'Acme Biotech Corp',
            'email'        => 'acme@example.com',
            'status'       => 'Active',
        ]);

        VendorReimbursement::create([
            'vendor_type'            => 'partner',
            'vendor_id'              => $partner->partner_id,
            'serms_reimbursement_id' => 12,
            'reference_no'           => 'RMB-12',
            'vendor_name'            => 'Acme Biotech Corp',
            'amount'                 => 2400,
            'status'                 => 'Approved',
            'synced_at'              => now(),
        ]);

        Http::fake([
            'http://serms-mock/api/serms/internal/reimbursements/by-vendor*' => Http::response([], 503),
        ]);

        $response = $this->getJson("/api/partners/{$partner->bp_code}/reimbursements");

        $response->assertOk()
            ->assertJsonPath('meta.serms_connected', false)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.reference_no', 'RMB-12');
    }

    public function test_merges_tin_and_vendor_name_matches_with_deduplication(): void
    {
        $supplier = Supplier::create([
            'supplier_name' => 'Bayan Telecommunications , Inc',
            'tin_number'    => '000-774-471-025',
            'status'        => 'Active',
        ]);

        Http::fake([
            'http://serms-mock/api/serms/internal/reimbursements/by-vendor?tin=*' => Http::response([
                'data' => [
                    [
                        'reimbursement_id' => 8,
                        'reference_no'     => 'RMB-8',
                        'tin_number'       => '000-774-471-025',
                        'vendor_name'      => 'Bayan Telecommunications , Inc',
                        'amount'           => 1.0,
                        'status'           => 'Pending',
                        'expense_date'     => '2012-08-09',
                    ],
                ],
            ], 200),
            'http://serms-mock/api/serms/internal/reimbursements/by-vendor?vendor_name=*' => Http::response([
                'data' => [
                    [
                        'reimbursement_id' => 11,
                        'reference_no'     => 'RMB-11',
                        'tin_number'       => '010-301-859-000',
                        'vendor_name'      => 'Bayan Telecommunications , Inc',
                        'amount'           => 1.79,
                        'status'           => 'Pending',
                        'expense_date'     => '2026-11-14',
                    ],
                    [
                        'reimbursement_id' => 8,
                        'reference_no'     => 'RMB-8',
                        'tin_number'       => '000-774-471-025',
                        'vendor_name'      => 'Bayan Telecommunications , Inc',
                        'amount'           => 1.0,
                        'status'           => 'Pending',
                        'expense_date'     => '2012-08-09',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson("/api/suppliers/{$supplier->supplier_id}/reimbursements");

        $response->assertOk()
            ->assertJsonPath('meta.serms_connected', true)
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('meta.total_amount', 2.79)
            ->assertJsonPath('data.0.reference_no', 'RMB-11')
            ->assertJsonPath('data.1.reference_no', 'RMB-8');
    }
}
