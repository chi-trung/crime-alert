<?php

// Issue #166: the Gemini model used to be frozen inside the endpoint URL
// ('gemini-pro' — Gemini 1.0 Pro, retired per Google's changelog, so the
// stock gemini path 4xx'd "model not found" forever) and unlike its
// siblings there was no *_MODEL env to override it without a code change.
// The endpoint is now built from GEMINI_MODEL and the model is also
// exposed under providers.gemini.model for parity with openai/deepseek/
// openrouter.
$geminiModel = env('GEMINI_MODEL', 'gemini-2.5-flash');

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
                // Issue #166: model env-overridable like the siblings — the
                // retired 'gemini-pro' used to be frozen into this string.
                'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/'.$geminiModel.':generateContent',
                'model' => $geminiModel,
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
                // Issue #166: the previous default (agentica-org/
                // deepcoder-14b-preview:free) is delisted — re-verified
                // against GET /api/v1/models on 2026-09-14: 445 ids live,
                // zero agentica/deepcoder matches — so a stock deploy
                // 4xx'd on every question. This slug was in the same live
                // fetch (an instruction-tuned general model, 262k ctx).
                // OpenRouter rotates its free tier, so override with
                // OPENROUTER_MODEL rather than trusting any baked default.
                'model' => env('OPENROUTER_MODEL', 'google/gemma-4-31b-it:free'),
                'referer' => env('OPENROUTER_REFERER'),
            ],
        ],
    ],

];
