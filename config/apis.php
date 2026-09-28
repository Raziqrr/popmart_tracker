<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External API Base URLs
    |--------------------------------------------------------------------------
    |
    | Base URLs for every API this app talks to, keyed by client. The
    | EndpointBuilder helper reads from here so nothing hardcodes a host,
    | and each environment (dev/qa/prod) can point at a different value.
    |
    */

    'popmart' => [
        'base_url' => env('POPMART_API_BASE_URL', 'https://prod-apac-api.popmart.com'),
    ],

    'server' => [
        'base_url' => env('SERVER_API_BASE_URL', env('APP_URL', 'http://localhost')),
    ],

];
