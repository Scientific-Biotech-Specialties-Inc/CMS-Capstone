<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HasRole
{
    /**
     * Accepts one or more comma-separated role names as middleware parameters.
     * Example usage:  ->middleware('role:Manager,Sales')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->get('auth_role', '');

        if (!in_array($userRole, $roles)) {
            return response()->json([
                'message' => 'Forbidden. This action is not allowed for your role.',
            ], 403);
        }

        return $next($request);
    }
}
