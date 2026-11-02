<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Provider
    |--------------------------------------------------------------------------
    |
    | Choose your free WhatsApp API provider:
    | - 'callmebot' (Recommended - 100% Free, unlimited after setup)
    | - 'waapi' (Free tier: 50 messages/month)
    | - 'ultramsg' (Free trial: 30 messages)
    | - 'textmebot' (Free alternative to CallMeBot)
    |
    */
    'provider' => env('WHATSAPP_PROVIDER', 'callmebot'),

    /*
    |--------------------------------------------------------------------------
    | CallMeBot Configuration (RECOMMENDED - 100% FREE)
    |--------------------------------------------------------------------------
    |
    | Setup Instructions:
    | 1. Save +34 644 71 15 30 in your phone contacts as "CallMeBot"
    | 2. Open WhatsApp and send this message to CallMeBot:
    |    "I allow callmebot to send me messages"
    | 3. You'll receive your API key in response
    | 4. Add the key to your .env file
    |
    | Documentation: https://www.callmebot.com/blog/free-api-whatsapp-messages/
    |
    */
    'callmebot_key' => env('CALLMEBOT_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | WaAPI Configuration (50 free messages/month)
    |--------------------------------------------------------------------------
    |
    | Sign up at: https://waapi.app
    |
    */
    'waapi_instance_id' => env('WAAPI_INSTANCE_ID'),
    'waapi_token' => env('WAAPI_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | UltraMsg Configuration (30 free messages trial)
    |--------------------------------------------------------------------------
    |
    | Sign up at: https://ultramsg.com
    |
    */
    'ultramsg_instance_id' => env('ULTRAMSG_INSTANCE_ID'),
    'ultramsg_token' => env('ULTRAMSG_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | TextMeBot Configuration (Free)
    |--------------------------------------------------------------------------
    |
    | Alternative to CallMeBot
    |
    */
    'textmebot_key' => env('TEXTMEBOT_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | OTP Settings
    |--------------------------------------------------------------------------
    */
    'otp' => [
        // OTP expiry time in minutes
        'expiry_minutes' => env('OTP_EXPIRY_MINUTES', 5),

        // Maximum OTP attempts before lockout
        'max_attempts' => env('OTP_MAX_ATTEMPTS', 5),

        // Cooldown between resend requests (seconds)
        'resend_cooldown' => env('OTP_RESEND_COOLDOWN', 60),

        // Maximum OTPs per phone per hour
        'hourly_limit' => env('OTP_HOURLY_LIMIT', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom WhatsApp API URL
    |--------------------------------------------------------------------------
    |
    | If you're using a custom/self-hosted WhatsApp API, set the base URL here.
    |
    */
    'api_url' => env('WHATSAPP_API_URL'),
    'app_key' => env('WHATSAPP_API_KEY'),
    'auth_key' => env('WHATSAPP_API_AUTH'),
];
