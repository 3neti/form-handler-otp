<?php

return [
    'driver' => env('OTP_HANDLER_DRIVER', 'unavailable'),
    /*
    |--------------------------------------------------------------------------
    | Application Label
    |--------------------------------------------------------------------------
    |
    | The application name shown in SMS messages.
    |
    */
    'label' => env('OTP_LABEL', config('app.name', 'Your App')),

    /*
    |--------------------------------------------------------------------------
    | txtcmdr OTP API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for txtcmdr external OTP API.
    |
    */
    'txtcmdr' => [
        'base_url' => env('TXTCMDR_API_URL', 'https://txtcmdr.test'),
        'api_token' => env('TXTCMDR_API_TOKEN'),
        'connect_timeout' => (int) env('TXTCMDR_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('TXTCMDR_TIMEOUT', 15),
        'verify_ssl' => (bool) env('TXTCMDR_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Resends
    |--------------------------------------------------------------------------
    |
    | Maximum number of times a user can resend OTP.
    | Default: 3 attempts
    |
    */
    'max_resends' => env('OTP_MAX_RESENDS', 3),

    /*
    |--------------------------------------------------------------------------
    | Resend Cooldown
    |--------------------------------------------------------------------------
    |
    | Cooldown period between resend requests (in seconds).
    | Default: 30 seconds
    |
    */
    'resend_cooldown' => env('OTP_RESEND_COOLDOWN', 30),
];
