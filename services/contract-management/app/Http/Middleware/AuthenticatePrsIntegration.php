<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticatePrsIntegration
{
    public function handle(Request $request, Closure $next, string $mode = 'strict'): Response
    {
        if ($mode === 'catalog' && config('services.prs.catalog_public_read') === true) {
            return $next($request);
        }

        $provided = trim((string) (
            $request->bearerToken()
            ?? $request->header('X-Integration-Secret')
            ?? $request->header('X-Internal-Secret')
            ?? $request->header('X-PRS-Secret')
            ?? ''
        ));

        $candidates = array_filter([
            (string) config('services.prs.inbound_key', ''),
            (string) config('services.prs.outbound_key', ''),
            (string) config('services.ts.internal_secret', ''),
        ], fn (string $v): bool => $v !== '');

        foreach ($candidates as $expected) {
            if ($provided !== '' && hash_equals($expected, $provided)) {
                return $next($request);
            }
        }

        if ($mode === 'catalog') {
            $networks = array_filter(array_map('trim', explode(',', (string) config('services.prs.catalog_networks'))));
            $remote = (string) $request->server('REMOTE_ADDR');
            if ($networks !== [] && $remote !== '' && IpUtils::checkIp($remote, array_values($networks))) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }
}
