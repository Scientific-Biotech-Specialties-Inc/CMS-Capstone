<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AdminUserProxyController extends Controller
{
    protected AuthService $authService;
    protected AuditLogService $auditLogService;

    public function __construct(AuthService $authService, AuditLogService $auditLogService)
    {
        $this->authService = $authService;
        $this->auditLogService = $auditLogService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'role_name' => 'required|string',
            'department_name' => 'required|string',
        ]);

        // Constraint: Sales & Marketing Only
        if (strcasecmp($request->department_name, 'Sales & Marketing') !== 0) {
            return response()->json(['message' => 'Unauthorized. Sales & Marketing only.'], 403);
        }

        // Constraint: Employee/Manager Only (allow department-scoped variants)
        $roleNameNormalized = ucwords(strtolower($request->role_name));
        $allowedRoles = ['Employee', 'Manager'];
        if (!in_array($roleNameNormalized, $allowedRoles)) {
            return response()->json(['message' => 'Unauthorized. Employee/Manager only.'], 403);
        }

        $token = $request->bearerToken();
        $sessionId = $request->header('X-Session-ID') ?: $request->cookie('session_id') ?: '';
        
        $roleName = $roleNameNormalized;

        $result = $this->authService->createUser([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'role_name' => $roleName,
            'department_name' => 'Sales & Marketing',
        ], $token, $sessionId);

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
                null // Skip department filter (IT Admin or creator department log override)
            );
        }

        return response()->json($result, 201);
    }
}
