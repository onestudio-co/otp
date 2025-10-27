<?php

return [
    // Success messages
    'otp_sent_successfully' => 'OTP sent successfully.',
    'otp_verified_successfully' => 'OTP verified successfully.',

    // Error messages
    'otp_send_failed' => 'Failed to send OTP.',
    'otp_expired_or_not_found' => 'OTP expired or not found.',
    'invalid_otp' => 'Invalid OTP.',
    'max_attempts_exceeded' => 'Maximum verification attempts exceeded. You are blocked for :minutes minutes.',
    'too_many_attempts' => 'Too many attempts. Please try again later.',
    'resend_delay_active' => 'Please wait :seconds seconds before requesting a new OTP.',

    // OTP message template
    'otp_message' => 'Your verification code is: :otp. Valid for :minutes minutes.',

    // Test mode messages
    'test_mode_enabled' => 'Test mode is enabled. OTP not sent via SMS.',
    'test_otp_generated' => 'Test OTP generated: :otp',

    // Rate limiting messages
    'rate_limit_exceeded' => 'Rate limit exceeded. Maximum :limit requests per hour allowed. Blocked for :minutes minutes.',
    'rate_limit_blocked' => 'Phone number is blocked due to rate limiting. Try again in :minutes minutes.',
];
