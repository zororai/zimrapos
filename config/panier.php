<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Panier API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the Panier API.
    |
    */
    'base_url' => env('PANIER_API_BASE_URL', 'http://localhost:3000/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Panier API Credentials
    |--------------------------------------------------------------------------
    |
    | Your Panier APP-ID and API-KEY credentials.
    |
    */
    'app_id' => env('PANIER_APP_ID', ''),
    'api_key' => env('PANIER_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limit configuration (500 calls per 5 minutes per IP).
    |
    */
    'rate_limit' => env('PANIER_RATE_LIMIT', 500),
    'rate_limit_minutes' => env('PANIER_RATE_LIMIT_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Request timeout in seconds.
    |
    */
    'timeout' => env('PANIER_TIMEOUT', 30),
];
