<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| Explicit allowlist only (OWASP A05). The SPA runs on :5001 under Vite in
| development and is served by the gateway on :5173. Production is
| same-origin (the gateway proxies both /cms/ and /api/*), so CORS is only
| exercised by local development.
|
| supports_credentials stays false until the auth token moves out of
| localStorage into an HttpOnly cookie (tracked separately).
|
*/

$origins = array_values(array_unique(array_filter(array_map(
    'trim',
    explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:5001,http://127.0.0.1:5001,http://localhost:5173,http://127.0.0.1:5173'
    ))
))));

$frontendUrl = trim((string) env('FRONTEND_URL', ''));
if ($frontendUrl !== '' && !in_array($frontendUrl, $origins, true)) {
    $origins[] = $frontendUrl;
}

return [

    'paths' => ['api/*', 'storage/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-Session-ID',
    ],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
