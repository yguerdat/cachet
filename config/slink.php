<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base URL of the Slink instance
    |--------------------------------------------------------------------------
    |
    | The public domain that serves shortened URLs. Set in .env (SLINK_BASE_URL).
    | The REST endpoint lives at this base URL plus `/rest/v3/short-urls`.
    |
    */
    'base_url' => env('SLINK_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | API key
    |--------------------------------------------------------------------------
    |
    | Generated via `shlink api-key:generate` on the server hosting Slink.
    |
    */
    'api_key' => env('SLINK_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Slug prefix
    |--------------------------------------------------------------------------
    |
    | When generating a short URL for a status notification, the slug hint
    | passed to the API is `{prefix}/{token}`, e.g. `status/abcd1234`.
    |
    */
    'slug_prefix' => env('SLINK_SLUG_PREFIX', 'status'),

    /*
    |--------------------------------------------------------------------------
    | HTTP request timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('SLINK_TIMEOUT', 5),

];
