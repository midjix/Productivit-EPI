<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | DSK — API REST du pointage RH (lecture seule, token Bearer)
    |--------------------------------------------------------------------------
    | `url` = racine du serveur ; `prefix` = préfixe des ressources OpenAPI (/dsknet/v2).
    | `token` : jeton statique facultatif ; sinon POST /login avec login/password.
    */
    'dsk' => [
        'url' => env('DSK_API_URL', ''),
        'prefix' => env('DSK_API_PREFIX', 'dsknet/v2'),
        'login' => env('DSK_API_LOGIN'),
        'password' => env('DSK_API_PASSWORD'),
        'token' => env('DSK_API_TOKEN'),
        'timeout' => (int) env('DSK_API_TIMEOUT', 60),
    ],

];
