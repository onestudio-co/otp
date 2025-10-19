<?php

return [
    // Success messages
    'otp_sent_successfully' => 'OTP sent successfully.',
    'otp_verified_successfully' => 'OTP verified successfully.',

    // Error messages
    'otp_send_failed' => 'Failed to send OTP.',
    'otp_expired_or_not_found' => 'OTP expired or not found.',
    'invalid_otp' => 'Invalid OTP.',
    'max_attempts_exceeded' => 'Maximum verification attempts exceeded. Please request a new OTP.',
    'too_many_attempts' => 'Too many attempts. Please try again later.',
    'resend_delay_active' => 'Please wait :seconds seconds before requesting a new OTP.',

    // OTP message template
    'otp_message' => 'Your verification code is: :otp. Valid for :minutes minutes.',
];
