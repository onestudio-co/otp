<?php

declare(strict_types=1);

namespace OneStudio\Otp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Carbon;
use OneStudio\Otp\OtpManager;

class OtpService
{
    protected $manager;

    public function __construct(OtpManager $manager)
    {
        $this->manager = $manager;
    }

    public function generate(string $phone): array
    {
        // Check if blocked
        if ($this->isBlocked($phone)) {
            return [
                'success' => false,
                'message' => 'Too many attempts. Please try again later.',
                'blocked_until' => Cache::get("otp_blocked:{$phone}")
            ];
        }

        // Check resend delay
        if (!$this->canResend($phone)) {
            $remainingTime = $this->getRemainingResendTime($phone);
            return [
                'success' => false,
                'message' => "Please wait {$remainingTime} seconds before requesting a new OTP.",
                'remaining_time' => $remainingTime
            ];
        }

        // Generate OTP
        $otp = $this->generateOtpCode();
        $expiryMinutes = (int) Config::get('otp.otp_expiry');

        // Store OTP in cache
        Cache::put("otp:{$phone}", [
            'code' => $otp,
            'attempts' => 0,
        ], Carbon::now()->addMinutes($expiryMinutes));

        // Set resend delay
        Cache::put("otp_last_sent:{$phone}", Carbon::now(), Carbon::now()->addSeconds((int) Config::get('otp.resend_delay')));

        // Send OTP
        $message = "Your verification code is: {$otp}. Valid for {$expiryMinutes} minutes.";
        $sent = $this->manager->driver()->send($phone, $message);

        return [
            'success' => $sent,
            'message' => $sent ? 'OTP sent successfully.' : 'Failed to send OTP.',
            'expires_in' => $expiryMinutes * 60
        ];
    }

    public function verify(string $phone, string $code): array
    {
        $otpData = Cache::get("otp:{$phone}");

        if (!$otpData) {
            return [
                'success' => false,
                'message' => 'OTP expired or not found.'
            ];
        }

        // Check attempts
        if ($otpData['attempts'] >= (int) Config::get('otp.max_attempts')) {
            $this->blockPhone($phone);
            Cache::forget("otp:{$phone}");
            return [
                'success' => false,
                'message' => 'Maximum verification attempts exceeded. Please request a new OTP.'
            ];
        }

        // Verify code
        if ($otpData['code'] === $code) {
            Cache::forget("otp:{$phone}");
            Cache::forget("otp_last_sent:{$phone}");
            return [
                'success' => true,
                'message' => 'OTP verified successfully.'
            ];
        }

        // Increment attempts
        $otpData['attempts']++;
        Cache::put("otp:{$phone}", $otpData, Carbon::now()->addMinutes((int) Config::get('otp.otp_expiry')));

        $remainingAttempts = (int) Config::get('otp.max_attempts') - $otpData['attempts'];

        return [
            'success' => false,
            'message' => 'Invalid OTP.',
            'remaining_attempts' => $remainingAttempts
        ];
    }

    protected function generateOtpCode(): string
    {
        $length = (int) Config::get('otp.otp_length');
        $randomNumber = (string) random_int(0, (int) pow(10, $length) - 1);
        return str_pad($randomNumber, $length, '0', STR_PAD_LEFT);
    }

    protected function isBlocked(string $phone): bool
    {
        return Cache::has("otp_blocked:{$phone}");
    }

    protected function blockPhone(string $phone): void
    {
        $duration = (int) Config::get('otp.block_duration');
        Cache::put("otp_blocked:{$phone}", Carbon::now()->addMinutes($duration), Carbon::now()->addMinutes($duration));
    }

    protected function canResend(string $phone): bool
    {
        return !Cache::has("otp_last_sent:{$phone}");
    }

    protected function getRemainingResendTime(string $phone): int
    {
        $lastSent = Cache::get("otp_last_sent:{$phone}");
        if (!$lastSent) {
            return (int) 0;
        }

        $resendDelay = (int) Config::get('otp.resend_delay');
        $elapsed = Carbon::now()->diffInSeconds($lastSent);
        return (int) max(0, $resendDelay - $elapsed);
    }
}
