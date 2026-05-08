<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base URL of the SMSeagle HTTP API v2
    |--------------------------------------------------------------------------
    |
    | Set in .env (SMSEAGLE_BASE_URL). The driver appends
    | `/api/v2/messages/sms` to this base URL.
    |
    */
    'base_url' => env('SMSEAGLE_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | API access token
    |--------------------------------------------------------------------------
    |
    | Generated in the SMSeagle web UI under "API tokens". The token must have
    | the "Messages" and "Modems" scopes enabled.
    |
    */
    'token' => env('SMSEAGLE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Default modem
    |--------------------------------------------------------------------------
    |
    | Modem number to use when sending. Leave null to let the gateway pick any
    | available modem from its rotation.
    |
    */
    'modem' => env('SMSEAGLE_MODEM'),

    /*
    |--------------------------------------------------------------------------
    | HTTP request timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('SMSEAGLE_TIMEOUT', 10),

];
