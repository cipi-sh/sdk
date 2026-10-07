<?php

declare(strict_types=1);

return [
    /*
    | Panel origin. HTTPS is required unless CIPI_ALLOW_HTTP=true (local only).
    | https://api.example.com and https://api.example.com/api are both accepted.
    */
    'base_url' => env('CIPI_BASE_URL', ''),

    /*
    | Sanctum token from `cipi api token create`. Keep this in the environment,
    | never in the repository. Grant only the abilities this app needs.
    */
    'token' => env('CIPI_TOKEN', ''),

    'timeout' => env('CIPI_TIMEOUT', 30),

    'connect_timeout' => env('CIPI_CONNECT_TIMEOUT', 10),

    'allow_http' => env('CIPI_ALLOW_HTTP', false),

    'max_retries' => env('CIPI_MAX_RETRIES', 2),
];
