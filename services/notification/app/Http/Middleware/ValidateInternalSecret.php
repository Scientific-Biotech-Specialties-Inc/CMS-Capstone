<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateInternalSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('app.internal_service_secret');
        $provided = (string) ($request->header('X-Internal-Secret') ?? '');

        // Constant-time comparison: prevents byte-by-byte timing attacks.
        // NOTE: never log $secret or the provided header value.
        if (!$secret || !hash_equals((string) $secret, $provided)) {
            return response()->json(['message' => 'Unauthorized internal request.'], 401);
        }

        return $next($request);
    }
}
