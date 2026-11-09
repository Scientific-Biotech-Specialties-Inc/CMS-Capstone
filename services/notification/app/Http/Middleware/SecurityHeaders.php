<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline HTTP security headers (OWASP A05: Security Misconfiguration).
 *
 * Deliberately does NOT set Content-Security-Policy: the SPA shell
 * (frontend/web/index.html) relies on an inline <style>/<script> block, Google
 * Fonts, and blob: URLs for the PDF viewer. A CSP must be introduced
 * separately (report-only first) with those sources explicitly allowed.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
