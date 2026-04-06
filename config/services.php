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

    'facebook_messenger' => [
        'enabled' => env('FB_MESSENGER_ENABLED', false),
        'notify_on_every_endorsement' => env('FB_MESSENGER_NOTIFY_ON_EVERY_ENDORSE', true),
        'bulk_threshold' => (int) env('FB_MESSENGER_BULK_THRESHOLD', 50),
        'page_access_token' => env('FB_MESSENGER_PAGE_ACCESS_TOKEN'),
        'rd_psid' => env('FB_MESSENGER_RD_PSID'),
        'approvals_url' => env('FB_MESSENGER_APPROVALS_URL'),
        'graph_api_version' => env('FB_MESSENGER_GRAPH_API_VERSION', 'v22.0'),
    ],

    'telegram_bot' => [
        'enabled' => env('TG_BOT_ENABLED', false),
        'notify_on_every_endorsement' => env('TG_NOTIFY_ON_EVERY_ENDORSE', true),
        'bulk_threshold' => (int) env('TG_BULK_THRESHOLD', 50),
        'bot_token' => env('TG_BOT_TOKEN'),
        'rd_chat_id' => env('TG_RD_CHAT_ID'),
        'webhook_secret' => env('TG_WEBHOOK_SECRET'),
    ],

];
