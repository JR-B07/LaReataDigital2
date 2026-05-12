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

    'mercadopago' => [
        'mode' => env('MERCADOPAGO_MODE', 'production'),
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'sandbox_access_token' => env('MERCADOPAGO_SANDBOX_ACCESS_TOKEN'),
        'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
        'sandbox_public_key' => env('MERCADOPAGO_SANDBOX_PUBLIC_KEY'),
    ],

    'conekta' => [
        'api_key' => env('CONEKTA_API_KEY', env('CONEKTA_API_KEY_PRIVATE')),
        'webhook_url' => env('CONEKTA_WEBHOOK_URL', env('APP_URL') . '/api/webhook/conekta'),
    ],

];
