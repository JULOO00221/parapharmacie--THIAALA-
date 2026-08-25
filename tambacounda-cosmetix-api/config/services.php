<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
    |--------------------------------------------------------------------------
    | Wave (mobile money — Sénégal)
    |--------------------------------------------------------------------------
    |
    | mock=true (the default) routes every "wave" payment through
    | MockWavePaymentProvider — never api.wave.com — so this integration
    | works end-to-end without a Wave Developer account. api_key/webhook_secret
    | stay unset (and unused) until Wave Developer access is granted and a
    | real WavePaymentProvider is written; see PaymentProviderFactory.
    |
    */
    'wave' => [
        'mock' => (bool) env('WAVE_MOCK', true),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
        'api_key' => env('WAVE_API_KEY'),
        'webhook_secret' => env('WAVE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp
    |--------------------------------------------------------------------------
    |
    | mock=true (the default) routes every notification through
    | MockWhatsAppProvider — never a real WhatsApp Business/Cloud API call
    | — so the whole notification system works end-to-end without WhatsApp
    | Business API access. api_url/api_token/phone_number_id stay unset
    | (and unused) until that access is granted and a real
    | WhatsAppProviderInterface implementation is written; see
    | WhatsAppProviderFactory. manager_phone is never hardcoded anywhere
    | in the codebase — always read from here.
    |
    */
    'whatsapp' => [
        'mock' => (bool) env('WHATSAPP_MOCK', true),
        'provider' => env('WHATSAPP_PROVIDER', 'mock'),
        'api_url' => env('WHATSAPP_API_URL'),
        'api_token' => env('WHATSAPP_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'manager_phone' => env('WHATSAPP_MANAGER_PHONE'),
    ],

];
