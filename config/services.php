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

    'ai' => [
        // The single provider the chatbot actually calls: gemini | openai | deepseek | openrouter
        'provider' => env('CHATBOT_PROVIDER', 'openrouter'),

        'providers' => [
            'gemini' => [
                'key' => env('GEMINI_API_KEY'),
                'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent',
            ],
            'openai' => [
                'key' => env('OPENAI_API_KEY'),
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
                'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
            ],
            'deepseek' => [
                'key' => env('DEEPSEEK_API_KEY'),
                'endpoint' => 'https://api.deepseek.com/v1/chat/completions',
                'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            ],
            'openrouter' => [
                'key' => env('OPENROUTER_API_KEY'),
                'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
                'model' => env('OPENROUTER_MODEL', 'agentica-org/deepcoder-14b-preview:free'),
                'referer' => env('OPENROUTER_REFERER'),
            ],
        ],
    ],

];
