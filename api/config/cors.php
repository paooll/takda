<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |---------------------------------------------------------------------------
    |
    | Takda's web client is a separate SPA (served from Cloudflare Pages in
    | production) that calls this API with a bearer token. Token auth means we
    | deliberately do NOT enable credentialed requests, which keeps the API
    | closed to cookie-based cross-site abuse.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        config('takda.frontend_url'),
        env('TAKDA_FRONTEND_URL', 'http://localhost:5173'),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
