<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'opay' => [
        'base_url' => env('OPAY_BASE_URL', 'https://sandbox-api-d.opayco.com'),
        'secret_key' => env('OPAY_SECRET_KEY'),
        'public_key' => env('OPAY_PUBLIC_KEY'),
        'merchant_id' => env('OPAY_MERCHANT_ID'),
    ],

    'truststack' => [
        'python_bin' => env('TRUESEAL_PYTHON_BIN', 'python3'),
        'engine_path' => env('TRUESEAL_ENGINE_PATH') ?: base_path('../python/truststack_forensics.py'),
    ],

];
