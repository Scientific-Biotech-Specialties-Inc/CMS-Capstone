<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Contract;
use Carbon\Carbon;
use Tests\TestCase;

final class ContractLifecycleStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Freeze time to a fixed date to ensure predictable diffs in tests.
        Carbon::setTestNow(Carbon::create(2026, 6, 4));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Scenario 1: Well within active (today + 60d)
     */
    public function test_contract_with_end_date_60_days_in_future_is_active(): void
    {
        $contract = new Contract([
            'end_date' => Carbon::today()->addDays(60)->toDateString(),
        ]);

        $this->assertSame('active', $contract->lifecycle_status);
    }

    /**
     * Scenario 2: Active at boundary (today + 31d)
     */
    public function test_contract_with_end_date_31_days_in_future_is_active(): void
    {
        $contract = new Contract([
            'end_date' => Carbon::today()->addDays(31)->toDateString(),
        ]);

        $this->assertSame('active', $contract->lifecycle_status);
    }

    /**
     * Scenario 3: Expiring at upper boundary (today + 30d)
     */
    public function test_contract_with_end_date_30_days_in_future_is_expiring(): void
    {
        $contract = new Contract([
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
        ]);

        $this->assertSame('expiring', $contract->lifecycle_status);
    }

    /**
     * Scenario 4: Expiring mid-range (today + 15d)
     */
    public function test_contract_with_end_date_15_days_in_future_is_expiring(): void
    {
        $contract = new Contract([
            'end_date' => Carbon::today()->addDays(15)->toDateString(),
        ]);

        $this->assertSame('expiring', $contract->lifecycle_status);
    }

    /**
     * Scenario 5: Expiring today (today + 0d)
     */
    public function test_contract_with_end_date_today_is_expiring(): void
    {
        $contract = new Contract([
            'end_date' => Carbon::today()->toDateString(),
        ]);

        $this->assertSame('expiring', $contract->lifecycle_status);
    }

    /**
     * Scenario 6: Expired yesterday (today - 1d)
     */
    public function test_contract_with_end_date_yesterday_is_expired(): void
    {
        $contract = new Contract([
            'end_date' => Carbon::today()->subDay()->toDateString(),
        ]);

        $this->assertSame('expired', $contract->lifecycle_status);
    }

    /**
     * Scenario 7: Expired long ago (today - 365d)
     */
    public function test_contract_with_end_date_one_year_ago_is_expired(): void
    {
        $contract = new Contract([
            'end_date' => Carbon::today()->subDays(365)->toDateString(),
        ]);

        $this->assertSame('expired', $contract->lifecycle_status);
    }

    /**
     * Scenario 8: Null end_date
     */
    public function test_contract_with_null_end_date_is_active(): void
    {
        $contract = new Contract([
            'end_date' => null,
        ]);

        $this->assertSame('active', $contract->lifecycle_status);
    }
}
