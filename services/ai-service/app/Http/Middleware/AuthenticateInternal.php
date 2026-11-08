<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * User-facing bearer-token auth for ai-service's Risk Assessment and Vendor
 * AI Suggestion endpoints, matching contract-management/notification's
 * AuthenticateInternal middleware pattern exactly (verifies against
 * auth-service, merges auth_id/auth_role/auth_permissions/auth_department
 * into the request).
 */
class AuthenticateInternal
{
    public function __construct(protected AuthService $authService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $authData = $this->authService->verifyToken($token);

        if (!$authData || !($authData['valid'] ?? false)) {
            return response()->json(['message' => 'Unauthenticated or session expired.'], 401);
        }

        $request->merge([
            'auth_user'        => $authData['user'],
            'auth_id'          => $authData['user']['id'],
            'auth_role'        => $authData['user']['role'],
            'auth_permissions' => $authData['user']['permissions'] ?? [],
            'auth_department'  => $authData['user']['department'] ?? null,
        ]);

        return $next($request);
    }
}
