<?php

return [
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
    | SMS Provider
    |--------------------------------------------------------------------------
    |
    | The SMS provider to use for sending OTP codes.
    | Supported: 'txtcmdr', 'engagespark', 'nexmo'
    |
    */
    'sms_provider' => env('OTP_SMS_PROVIDER', 'engagespark'),
    
    /*
    |--------------------------------------------------------------------------
    | txtcmdr OTP API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for txtcmdr external OTP API.
    |
    */
    'txtcmdr' => [
        'base_url' => env('TXTCMDR_API_URL', 'http://txtcmdr.test'),
        'api_token' => env('TXTCMDR_API_TOKEN'),
        'timeout' => env('TXTCMDR_TIMEOUT', 30),
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
