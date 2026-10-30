<?php

return [

    // Credenciales de servicios externos (WhatsApp Cloud API, Meta y Groq)

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

    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'test_phone'      => env('WHATSAPP_TEST_PHONE'),
        'access_token'    => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token'    => env('WHATSAPP_VERIFY_TOKEN', env('VERIFY_TOKEN')),
        'app_secret'      => env('WHATSAPP_APP_SECRET', env('META_APP_SECRET')),
        'api_version'     => env('WHATSAPP_API_VERSION', 'v22.0'),
    ],

    'meta' => [
        'app_id'     => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET', env('WHATSAPP_APP_SECRET', '')),
        'config_id'  => env('META_CONFIG_ID', ''),
        'waba_id'    => env('META_WABA_ID'),
    ],

    'groq' => [
        'api_key'       => env('GROQ_API_KEY', env('TOKEN_OPENAI')),
        'model'         => env('GROQ_MODEL', 'qwen/qwen3.8-27b'),
        'whisper_model' => env('GROQ_WHISPER_MODEL', 'whisper-large-v3-turbo'),
    ],

];
