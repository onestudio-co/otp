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

    // Test mode configuration
    'test_mode' => env('OTP_TEST_MODE', false),
    'test_otp' => env('OTP_TEST_CODE', '8888'),
    'test_numbers' => [
        '+1234567890',
        '+9876543210',
        // Add more test numbers as needed
    ],
];
