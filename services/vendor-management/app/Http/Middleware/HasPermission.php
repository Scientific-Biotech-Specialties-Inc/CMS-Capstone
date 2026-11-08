<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\AuditLogService;

class HasPermission
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $role = $request->get('auth_role');
        if (in_array($role, ['Admin', 'Super Admin'], true)) {
            return $next($request);
        }

        $permissions = $request->get('auth_permissions', []);
        $requiredPerms = array_map('trim', explode('|', $permission));

        $allowed = false;
        foreach ($requiredPerms as $req) {
            if (in_array($req, $permissions, true)) {
                $allowed = true;
                break;
            }
            if (preg_match('/^cms\.([^.]+)\.([^.]+)$/', $req, $matches)) {
                $resource = $matches[1];
                $action = $matches[2];
                $alts = [
                    "{$action}-{$resource}",
                    "{$resource}.{$action}",
                    "{$action}_{$resource}",
                    "{$resource}-{$action}",
                    "{$action} {$resource}",
                ];
                foreach ($alts as $alt) {
                    if (in_array($alt, $permissions, true)) {
                        $allowed = true;
                        break 2;
                    }
                }
            }
        }

        if (!$allowed) {
            // Log permission denial
            $userId = $request->get('auth_id');
            $this->auditLogService->log(
                'permission_denied',
                'System',
                0,
                $userId,
                [],
                ['required_permission' => $permission]
            );

            return response()->json([
                'message' => 'Forbidden. You do not have the required permission: ' . $permission
            ], 403);
        }

        return $next($request);
    }
}

