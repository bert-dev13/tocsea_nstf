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

    'openweathermap' => [
        'key' => env('OPENWEATHERMAP_API_KEY'),
    ],

    'together' => [
        'api_key' => env('TOGETHER_API_KEY'),
        'base_url' => env('TOGETHER_API_BASE_URL', 'https://api.together.xyz/v1'),
        'model' => env('TOGETHER_MODEL', 'meta-llama/Llama-3.2-3B-Instruct-Turbo'),
        'timeout' => (int) env('TOGETHER_TIMEOUT', 60),
        'max_tokens' => (int) env('TOGETHER_MAX_TOKENS', 800),
        'temperature' => (float) env('TOGETHER_TEMPERATURE', 0.3),
    ],

];
