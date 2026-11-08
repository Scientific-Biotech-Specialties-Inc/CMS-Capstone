<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIntegrationSecret
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) ($request->header('X-Internal-Secret') ?? '');
        $expectedSecret = (string) env('INTERNAL_SERVICE_SECRET');

        // Constant-time comparison: prevents timing attacks on the shared secret.
        if ($expectedSecret === '' || !hash_equals($expectedSecret, $secret)) {
            return response()->json([
                'message' => 'Forbidden. Invalid or missing integration secret.'
            ], 403);
        }

        return $next($request);
    }
}
