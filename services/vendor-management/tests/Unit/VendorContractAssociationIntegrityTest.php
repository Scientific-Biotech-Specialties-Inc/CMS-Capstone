<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\AttachContractToVendor;
use App\Exceptions\ConflictException;
use App\Http\Resources\VendorContractResource;
use App\Models\BusinessPartner;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\VendorContractAssociation;
use App\Payloads\AttachContractPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

final class VendorContractAssociationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_association_throws_conflict_exception(): void
    {
        $contract = Contract::create([
            'contract_id' => 1,
            'description' => 'Test Contract',
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addYear(),
        ]);

        $supplier = Supplier::create([
            'supplier_name' => 'Supplier A',
        ]);

        VendorContractAssociation::create([
            'vendor_type' => 'supplier',
            'vendor_id' => $supplier->supplier_id,
            'contract_id' => $contract->contract_id,
            'attached_by' => 10,
        ]);

        $action = resolve(AttachContractToVendor::class);
        $payload = new AttachContractPayload(
            vendorType: 'supplier',
            vendorId: $supplier->supplier_id,
            contractId: $contract->contract_id,
            attachedBy: 10
        );

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('This contract is already linked to another vendor.');

        $action->handle($payload);
    }

    public function test_deleting_supplier_cascade_deletes_associations(): void
    {
        $contract = Contract::create([
            'contract_id' => 2,
            'description' => 'Test Contract 2',
        ]);

        $supplier = Supplier::create([
            'supplier_name' => 'Supplier B',
        ]);

        $assoc = VendorContractAssociation::create([
            'vendor_type' => 'supplier',
            'vendor_id' => $supplier->supplier_id,
            'contract_id' => $contract->contract_id,
            'attached_by' => 10,
        ]);

        $this->assertDatabaseHas('vendor_contract_associations', ['id' => $assoc->id]);

        $supplier->delete();

        $this->assertDatabaseMissing('vendor_contract_associations', ['id' => $assoc->id]);
    }

    public function test_deleting_business_partner_cascade_deletes_associations(): void
    {
        $contract = Contract::create([
            'contract_id' => 3,
            'description' => 'Test Contract 3',
        ]);

        $partner = BusinessPartner::create([
            'bp_code' => 'BP-991',
            'partner_name' => 'Partner A',
        ]);

        $assoc = VendorContractAssociation::create([
            'vendor_type' => 'partner',
            'vendor_id' => $partner->partner_id,
            'contract_id' => $contract->contract_id,
            'attached_by' => 10,
        ]);

        $this->assertDatabaseHas('vendor_contract_associations', ['id' => $assoc->id]);

        $partner->delete();

        $this->assertDatabaseMissing('vendor_contract_associations', ['id' => $assoc->id]);
    }

    public function test_engagement_status_derived_correctly_for_active(): void
    {
        $contract = Contract::create([
            'contract_id' => 4,
            'end_date' => Carbon::today()->addDays(31),
        ]);

        $assoc = VendorContractAssociation::create([
            'vendor_type' => 'supplier',
            'vendor_id' => 1,
            'contract_id' => $contract->contract_id,
        ]);

        $resource = new VendorContractResource($assoc->load('contract'));
        $data = $resource->toArray(new Request());

        $this->assertEquals('active', $data['engagement_status']);
    }

    public function test_engagement_status_derived_correctly_for_expiring(): void
    {
        $contract = Contract::create([
            'contract_id' => 5,
            'end_date' => Carbon::today()->addDays(15),
        ]);

        $assoc = VendorContractAssociation::create([
            'vendor_type' => 'supplier',
            'vendor_id' => 1,
            'contract_id' => $contract->contract_id,
        ]);

        $resource = new VendorContractResource($assoc->load('contract'));
        $data = $resource->toArray(new Request());

        $this->assertEquals('expiring', $data['engagement_status']);
    }

    public function test_engagement_status_derived_correctly_for_expired(): void
    {
        $contract = Contract::create([
            'contract_id' => 6,
            'end_date' => Carbon::today()->subDay(),
        ]);

        $assoc = VendorContractAssociation::create([
            'vendor_type' => 'supplier',
            'vendor_id' => 1,
            'contract_id' => $contract->contract_id,
        ]);

        $resource = new VendorContractResource($assoc->load('contract'));
        $data = $resource->toArray(new Request());

        $this->assertEquals('expired', $data['engagement_status']);
    }
}
