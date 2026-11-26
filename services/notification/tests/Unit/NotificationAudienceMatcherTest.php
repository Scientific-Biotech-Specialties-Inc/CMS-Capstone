<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NotificationAudienceMatcher;
use PHPUnit\Framework\TestCase;

final class NotificationAudienceMatcherTest extends TestCase
{
    public function test_matches_exact_role_and_expands_super_admin_to_admin(): void
    {
        $matcher = new NotificationAudienceMatcher();

        $adminNotif = ['target_roles' => 'Admin, Manager', 'target_user_id' => null];

        $this->assertTrue($matcher->matches($adminNotif, 'Admin', 1));
        $this->assertTrue($matcher->matches($adminNotif, 'Super Admin', 2));
        $this->assertTrue($matcher->matches($adminNotif, 'CEO', 3));
        $this->assertTrue($matcher->matches($adminNotif, 'Manager', 4));
        $this->assertFalse($matcher->matches($adminNotif, 'Sales', 5));
    }

    public function test_enforces_target_user_id_isolation(): void
    {
        $matcher = new NotificationAudienceMatcher();

        $userScoped = ['target_roles' => 'Sales', 'target_user_id' => 42];

        $this->assertTrue($matcher->matches($userScoped, 'Sales', 42));
        $this->assertFalse($matcher->matches($userScoped, 'Sales', 99));
        $this->assertFalse($matcher->matches($userScoped, 'Manager', 42));
    }
}
