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

    'psgc_api' => [
        'token' => env('PSGC_API_TOKEN'),
        'version' => env('PSGC_API_VERSION', 'Q2_2024'),
    ],

    'ollama' => [
        'enabled' => env('OLLAMA_ENABLED', true),
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_CAPTION_MODEL', 'qwen2.5:3b'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 30),
    ],

    // Hedera Consensus Service anchoring. The operator account id / private key
    // live ONLY in the Node bridge's own env (hedera/.env), never here. Laravel
    // only knows how to reach the bridge and the public read endpoints.
    'hedera' => [
        'enabled' => env('HEDERA_ENABLED', false),
        'bridge_url' => env('HEDERA_BRIDGE_URL', 'http://localhost:3001'),
        'bridge_timeout' => (int) env('HEDERA_BRIDGE_TIMEOUT', 20),
        'topic_id' => env('HEDERA_TOPIC_ID'),
        'network' => env('HEDERA_NETWORK', 'testnet'),
        'mirror_url' => env('HEDERA_MIRROR_URL', 'https://testnet.mirrornode.hedera.com'),
        'mirror_timeout' => (int) env('HEDERA_MIRROR_TIMEOUT', 8),
        'explorer_url' => env('HEDERA_EXPLORER_URL', 'https://hashscan.io/testnet'),
    ],

    'telegram_bot' => [
        'enabled' => env('TG_BOT_ENABLED', false),
        'notify_on_every_endorsement' => env('TG_NOTIFY_ON_EVERY_ENDORSE', true),
        'notify_on_rd_approval' => env('TG_NOTIFY_ON_RD_APPROVAL', true),
        'notify_on_rd_approval_bulk_only' => env('TG_NOTIFY_ON_RD_APPROVAL_BULK_ONLY', true),
        'bulk_threshold' => (int) env('TG_BULK_THRESHOLD', 50),
        'bot_token' => env('TG_BOT_TOKEN'),
        'chat_ids' => env('TG_CHAT_IDS'),
        'rd_chat_id' => env('TG_RD_CHAT_ID'),
        'webhook_secret' => env('TG_WEBHOOK_SECRET'),
    ],

];
