<?php

namespace Tests\Unit;

use App\Models\Supplier;
use App\Models\BusinessPartner;
use App\Services\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DuplicateDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = resolve(DuplicateDetectionService::class);
    }

    public function test_exact_duplicate_detected_on_same_tin_number(): void
    {
        Supplier::create([
            'supplier_name' => 'Supplier A',
            'tin_number' => '123-456-789',
        ]);

        $detection = $this->service->detect(
            'suppliers',
            'tin_number',
            '123-456-789',
            'supplier_name',
            'Supplier B',
            null,
            'supplier_id'
        );

        $this->assertTrue($detection['exact_duplicate']);
    }

    public function test_no_exact_duplicate_when_tin_is_unique(): void
    {
        Supplier::create([
            'supplier_name' => 'Supplier A',
            'tin_number' => '123-456-789',
        ]);

        $detection = $this->service->detect(
            'suppliers',
            'tin_number',
            '987-654-321',
            'supplier_name',
            'Supplier B',
            null,
            'supplier_id'
        );

        $this->assertFalse($detection['exact_duplicate']);
    }

    public function test_fuzzy_match_returns_warning_above_threshold(): void
    {
        Supplier::create([
            'supplier_name' => 'Acme Corporation',
            'tin_number' => '123-456-789',
        ]);

        $detection = $this->service->detect(
            'suppliers',
            'tin_number',
            '987-654-321',
            'supplier_name',
            'Acme Corp',
            null,
            'supplier_id'
        );

        $this->assertCount(1, $detection['fuzzy_warnings']);
        $this->assertEquals('Acme Corporation', $detection['fuzzy_warnings'][0]['name']);
    }

    public function test_fuzzy_match_no_warning_below_threshold(): void
    {
        Supplier::create([
            'supplier_name' => 'Acme Corporation',
            'tin_number' => '123-456-789',
        ]);

        $detection = $this->service->detect(
            'suppliers',
            'tin_number',
            '987-654-321',
            'supplier_name',
            'Totally Different Name',
            null,
            'supplier_id'
        );

        $this->assertCount(0, $detection['fuzzy_warnings']);
    }

    public function test_self_excluded_on_update_check(): void
    {
        $supplier = Supplier::create([
            'supplier_name' => 'Supplier A',
            'tin_number' => '123-456-789',
        ]);

        $detection = $this->service->detect(
            'suppliers',
            'tin_number',
            '123-456-789',
            'supplier_name',
            'Supplier A',
            $supplier->supplier_id,
            'supplier_id'
        );

        $this->assertFalse($detection['exact_duplicate']);
        // The fuzzy list should not include itself
        $this->assertCount(0, $detection['fuzzy_warnings']);
    }
}
