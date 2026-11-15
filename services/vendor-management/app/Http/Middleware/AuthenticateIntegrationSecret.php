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
        $secret = trim((string) ($request->header('X-Internal-Secret') ?? $request->bearerToken() ?? ''));
        $candidates = array_filter([
            (string) env('INTERNAL_SERVICE_SECRET', ''),
            (string) env('TS_TO_CMS_INTEGRATION_KEY', ''),
        ], fn (string $value): bool => $value !== '');

        foreach ($candidates as $expectedSecret) {
            if ($secret !== '' && hash_equals($expectedSecret, $secret)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Forbidden. Invalid or missing integration secret.',
        ], 403);
    }
}
