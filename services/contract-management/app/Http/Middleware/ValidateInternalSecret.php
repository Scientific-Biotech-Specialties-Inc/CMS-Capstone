<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates internal service-to-service calls via the shared
 * X-Internal-Secret header, matching the pattern already used inline by
 * InternalAuditController and by the other services' internal.secret alias.
 */
class ValidateInternalSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) ($request->header('X-Internal-Secret') ?? '');
        $candidates = array_filter([
            (string) env('INTERNAL_SERVICE_SECRET', ''),
            (string) env('SERMS_INTERNAL_SECRET', ''),
        ]);

        // Constant-time comparison: prevents byte-by-byte timing attacks
        // against the shared internal service secret.
        $matched = false;
        foreach ($candidates as $secret) {
            if (hash_equals($secret, $provided)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            return response()->json(['message' => 'Unauthorized internal request.'], 401);
        }

        return $next($request);
    }
}
