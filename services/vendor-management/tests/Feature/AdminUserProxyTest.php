<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminUserProxyTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Test successful user creation (Sales & Marketing + Employee).
     */
    public function test_create_user_sales_marketing_employee_success()
    {
        // 1. Mock internal auth verification
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => ['id' => 1, 'role' => 'Admin', 'permissions' => ['cms.users.create'], 'department' => 'Sales & Marketing']
            ]),
            // Mock fetching roles
            'http://auth-service:8000/api/admin/role-options' => Http::response([
                ['id' => 10, 'name' => 'Employee'],
                ['id' => 11, 'name' => 'Manager']
            ]),
            // Mock fetching departments
            'http://auth-service:8000/api/admin/department-options' => Http::response([
                ['id' => 50, 'name' => 'Sales & Marketing'],
                ['id' => 51, 'name' => 'Marketing']
            ]),
            // Mock the actual creation call to auth-service
            'http://auth-service:8000/api/admin/users' => Http::response([
                'message' => 'User created successfully.',
                'user' => ['email' => 'newuser@sales-marketing.com']
            ], 201)
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer admin-token',
        ])->postJson('/api/admin/users', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'newuser@sales-marketing.com',
            'role_name' => 'Employee',
            'department_name' => 'Sales & Marketing'
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('user.email', 'newuser@sales-marketing.com');
    }

    /**
     * Test blocked user creation for non-Sales-&-Marketing department.
     */
    public function test_create_user_wrong_department_blocked()
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => ['id' => 1, 'role' => 'Admin', 'permissions' => ['cms.users.create'], 'department' => 'Sales & Marketing']
            ])
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer admin-token',
        ])->postJson('/api/admin/users', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@marketing.com',
            'role_name' => 'Employee',
            'department_name' => 'Marketing' // Forbidden
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Unauthorized. This admin can only create users for the Sales & Marketing department.']);
    }

    /**
     * Test blocked user creation with unauthorized role.
     */
    public function test_create_user_unauthorized_role_blocked()
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => ['id' => 1, 'role' => 'Admin', 'permissions' => ['cms.users.create'], 'department' => 'Sales & Marketing']
            ])
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer admin-token',
        ])->postJson('/api/admin/users', [
            'first_name' => 'Big',
            'last_name' => 'Boss',
            'email' => 'boss@sales-marketing.com',
            'role_name' => 'Admin', // Forbidden
            'department_name' => 'Sales & Marketing'
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Unauthorized. This admin can only assign Employee or Manager roles.']);
    }
}
