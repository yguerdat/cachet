<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed phone-number prefixes
    |--------------------------------------------------------------------------
    |
    | Regular expression a submitted phone number must match. Defaults to
    | Switzerland + neighbouring EU country codes — a deliberate whitelist
    | that prevents SMS abuse to unallocated codes (e.g. +999…). Override via
    | the SUBSCRIBE_PHONE_REGEX env var if you need to onboard subscribers
    | from outside this region.
    |
    */
    'phone_regex' => env(
        'SUBSCRIBE_PHONE_REGEX',
        '/^\+(41|33|49|39|43|32|31|34|44|351|352|420|45|46|47|48|358|371|372|370|353)\d{6,12}$/',
    ),

];
