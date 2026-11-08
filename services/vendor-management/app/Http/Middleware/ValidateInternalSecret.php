<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates internal service-to-service calls via the shared
 * X-Internal-Secret header, matching the inline check already used by
 * InternalAuditController and the equivalent middleware in
 * contract-management/ai-service.
 */
class ValidateInternalSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = env('INTERNAL_SERVICE_SECRET');
        $provided = (string) ($request->header('X-Internal-Secret') ?? '');

        // Constant-time comparison: prevents byte-by-byte timing attacks
        // against the shared internal service secret.
        if (!$secret || !hash_equals((string) $secret, $provided)) {
            return response()->json(['message' => 'Unauthorized internal request.'], 401);
        }

        return $next($request);
    }
}
