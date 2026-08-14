<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe
    |--------------------------------------------------------------------------
    */
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | eNom
    |--------------------------------------------------------------------------
    */
    'enom' => [
        'username' => env('ENOM_USERNAME'),
        'password' => env('ENOM_PASSWORD'),
        'sandbox' => env('ENOM_SANDBOX', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | WHM / cPanel
    |--------------------------------------------------------------------------
    */
    'whm' => [
        'host' => env('WHM_HOST'),
        'username' => env('WHM_USERNAME'),
        'token' => env('WHM_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Apple Push Notification Service (APNs)
    |--------------------------------------------------------------------------
    */
    'apn' => [
        'key_id' => env('APN_KEY_ID'),
        'team_id' => env('APN_TEAM_ID'),
        'bundle_id' => env('APN_BUNDLE_ID'),
        'key_path' => env('APN_KEY_PATH'),
        'sandbox' => env('APN_SANDBOX', false),
    ],
];
