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
        'token' => env('POSTMARK_TOKEN'),
        'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
        'membership_product_id' => env('STRIPE_MEMBERSHIP_PRODUCT_ID'),
        'fee_coverage_product_id' => env('STRIPE_FEE_COVERAGE_PRODUCT_ID'),
    ],

    'zeffy' => [
        'webhook_secret' => env('ZEFFY_WEBHOOK_SECRET'),
        // Note: Zeffy doesn't have a direct API - only Zapier integration
        'organization_name' => env('ZEFFY_ORGANIZATION_NAME', 'Corvallis Music Collective'),
    ],

    'github' => [
        'token' => env('GITHUB_TOKEN'),
        'repository' => [
            'owner' => env('GITHUB_REPO_OWNER'),
            'name' => env('GITHUB_REPO_NAME'),
        ],
    ],

];
