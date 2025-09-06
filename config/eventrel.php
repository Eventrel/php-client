<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Eventrel API Token
    |--------------------------------------------------------------------------
    |
    | Your team-scoped Eventrel API token. You can find this in your Eventrel 
    | dashboard under Settings -> API Tokens. This token is already scoped to 
    | your team, so you don't need to specify a team when sending webhooks.
    |
    | Keep this secure and never commit it to version control.
    |
    */

    'api_token' => env('EVENTREL_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Eventrel API Version
    |--------------------------------------------------------------------------
    |
    | The version of the Eventrel API to use. You can find the available
    | versions in the Eventrel API documentation.
    |
    */

    'version' => env('EVENTREL_API_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the Eventrel API. You typically won't need to change
    | this unless you're using a self-hosted version of Eventrel.
    |
    */

    'base_url' => env('EVENTREL_BASE_URL', 'https://api.eventrel.sh'),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | HTTP request timeout in seconds. Webhook delivery can take a while
    | if your endpoints are slow, so 30 seconds is a reasonable default.
    |
    */

    'timeout' => env('EVENTREL_TIMEOUT', 30),
];
