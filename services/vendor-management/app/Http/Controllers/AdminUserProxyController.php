<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminUserProxyController extends Controller
{
    protected AuthService $authService;
    protected AuditLogService $auditLogService;

    public function __construct(AuthService $authService, AuditLogService $auditLogService)
    {
        $this->authService = $authService;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Proxy user creation to auth-service with CMS-specific constraints.
     */
    public function store(Request $request)
    {
        // 1. Initial Validation
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'role_name' => 'required|string', // We use name for easier logic
            'department_name' => 'required|string',
        ]);

        // 2. Enforce CMS Constraints
        if (strcasecmp($request->department_name, 'Sales & Marketing') !== 0) {
            return response()->json([
                'message' => 'Unauthorized. This admin can only create users for the Sales & Marketing department.'
            ], 403);
        }

        $allowedRoles = ['Employee', 'Manager'];
        $roleMatched = false;
        foreach ($allowedRoles as $role) {
            if (strcasecmp($request->role_name, $role) === 0) {
                $roleMatched = true;
                break;
            }
        }

        if (!$roleMatched) {
            return response()->json([
                'message' => 'Unauthorized. This admin can only assign Employee or Manager roles.'
            ], 403);
        }

        // 3. Resolve IDs from Auth Service
        // We fetch current roles/depts from auth-service to get the correct IDs
        $token = $request->bearerToken();
        $rolesResponse = HttpProxy::get('admin/role-options', $token);
        $deptsResponse = HttpProxy::get('admin/department-options', $token);

        if (!$rolesResponse->successful() || !$deptsResponse->successful()) {
            $status = !$rolesResponse->successful() ? $rolesResponse->status() : $deptsResponse->status();
            $msg = !$rolesResponse->successful() ? ($rolesResponse->json('message') ?? 'Failed to retrieve roles') : ($deptsResponse->json('message') ?? 'Failed to retrieve departments');
            return response()->json(['message' => $msg], $status);
        }

        $roles = $rolesResponse->json();
        $depts = $deptsResponse->json();

        // Map standard roles
        $mappedRoleName = ucwords(strtolower($request->role_name));

        $roleId = $this->findIdByName($roles, $mappedRoleName);
        $deptId = $this->findIdByName($depts, $request->department_name);

        if (!$roleId || !$deptId) {
            return response()->json(['message' => 'Failed to resolve Role or Department ID from Auth Service.'], 422);
        }

        // 4. Forward to Auth Service
        $authRequestData = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'role_id' => $roleId,
            'department_id' => $deptId,
        ];

        $result = $this->authService->createUser($authRequestData, $token);

        if (isset($result['error'])) {
            return response()->json($result, $result['status'] ?? 500);
        }

        // Audit Log User Creation
        $creatorRole = $request->get('auth_role');
        $creatorDepartment = $request->get('auth_department');
        $shouldLog = ($creatorDepartment === 'Sales & Marketing') || ($creatorRole === 'IT Admin');

        if ($shouldLog) {
            $this->auditLogService->log(
                'user_created',
                'User',
                $result['user']['id'] ?? 0,
                $request->get('auth_id'),
                [],
                [
                    'email' => $request->email,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'role' => $request->role_name,
                    'department' => $request->department_name
                ],
                null // Skip department filter
            );
        }

        return response()->json($result, 201);
    }

    private function findIdByName($items, $name) {
        foreach ($items as $item) {
            if (strcasecmp($item['name'], $name) === 0) {
                return $item['id'];
            }
        }
        return null;
    }
}

/**
 * Simple Helper for internal calls
 */
class HttpProxy {
    public static function get($endpoint, $token) {
        $baseUrl = env('AUTH_SERVICE_URL', 'http://auth-service:8000/api');
        $sessionId = (request()->header('X-Session-ID') ?: request()->cookie('session_id')) ?: '';
        return \Illuminate\Support\Facades\Http::withToken($token)
            ->withHeaders(['X-Session-ID' => $sessionId])
            ->get("{$baseUrl}/{$endpoint}");
    }
}
