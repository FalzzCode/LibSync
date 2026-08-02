<?php

$googleRedirect = env('GOOGLE_REDIRECT_URI');

if (! is_string($googleRedirect) || trim($googleRedirect) === '' || str_contains($googleRedirect, '${APP_URL}')) {
    $googleRedirect = rtrim((string) env('APP_URL', 'http://localhost'), '/').'/auth/google/callback';
}

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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // APP_URL is the only domain-specific value required for OAuth. An
        // explicit URI remains supported for providers that use a separate
        // auth hostname.
        'redirect' => $googleRedirect,
        'allowed_domain' => env('GOOGLE_ALLOWED_DOMAIN'),
        // New Google identities become least-privilege student accounts. Set
        // this to false only when a librarian wants invite-only onboarding.
        'auto_register_students' => (bool) env('GOOGLE_AUTO_REGISTER_STUDENTS', true),
    ],

];
