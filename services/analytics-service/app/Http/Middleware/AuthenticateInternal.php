<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * User-facing bearer-token auth for the Analytics endpoints, matching the
 * other services' AuthenticateInternal middleware pattern exactly.
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
