<?php

declare(strict_types=1);

return [
    'default' => env('OTP_PROVIDER', 'twilio'),

    'providers' => [
        'twilio' => [
            'driver' => 'twilio',
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
        'unifonic' => [
            'driver' => 'unifonic',
            'app_sid' => env('UNIFONIC_APP_SID'),
            'sender_id' => env('UNIFONIC_SENDER_ID'),
        ],
    ],

    'otp_length' => env('OTP_LENGTH', 4),
    'otp_expiry' => env('OTP_EXPIRY', 5), // minutes
    'max_attempts' => env('MAX_ATTEMPTS', 3),
    'resend_delay' => env('RESEND_DELAY', 60), // seconds
    'block_duration' => env('BLOCK_DURATION', 30), // minutes after max attempts
];
