<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('CLAMAV_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | ClamAV Daemon Connection
    |--------------------------------------------------------------------------
    |
    | The hostname and port of the clamd daemon. In Docker, this points to
    | the clamav sidecar service via the shared Docker network.
    |
    */
    'host' => env('CLAMD_HOST', '127.0.0.1'),
    'port' => (int) env('CLAMD_PORT', 3310),

    /*
    |--------------------------------------------------------------------------
    | Connection Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum seconds to wait for clamd to respond before treating it as
    | an error. The scan itself is fast once the daemon is running.
    |
    */
    'timeout' => (int) env('CLAMD_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Scanner Unavailable Behaviour
    |--------------------------------------------------------------------------
    |
    | Malware detections are always blocked. If clamd is unreachable or returns
    | a scanner error, uploads are allowed and the API response includes a
    | scan_warning value so the frontend can show a note to the user.
    |
    */
];
