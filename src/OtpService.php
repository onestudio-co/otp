<?php

declare(strict_types=1);

namespace OneStudio\Otp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
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
                'message' => Lang::get('otp::otp.too_many_attempts'),
                'blocked_until' => Cache::get("otp_blocked:{$phone}")
            ];
        }
        $remainingTime = $this->getRemainingResendTime($phone);
        // Check resend delay
        if (!$this->canResend($phone)) {
            return [
                'success' => false,
                'message' => Lang::get('otp::otp.resend_delay_active', ['seconds' => $remainingTime]),
                'remaining_time' => $remainingTime
            ];
        }

        // Check if test mode is enabled or phone is in test numbers
        $isTestMode = $this->isTestMode($phone);
        
        // Generate OTP
        $otp = $isTestMode ? $this->getTestOtp() : $this->generateOtpCode();
        $expiryMinutes = (int) Config::get('otp.otp_expiry');

        // Store OTP in cache
        Cache::put("otp:{$phone}", [
            'code' => $otp,
            'attempts' => 0,
            'is_test' => $isTestMode,
        ], Carbon::now()->addMinutes($expiryMinutes));

        // Set resend delay
        Cache::put("otp_last_sent:{$phone}", Carbon::now(), Carbon::now()->addSeconds((int) Config::get('otp.resend_delay')));

        // Send OTP (skip sending in test mode)
        if ($isTestMode) {
            return [
                'success' => true,
                'message' => Lang::get('otp::otp.otp_sent_successfully'),
                'expires_in' => $expiryMinutes * 60,
                'remaining_time' => $remainingTime,
                'test_mode' => true,
                'test_otp' => $otp
            ];
        }

        // Send actual OTP
        $message = Lang::get('otp::otp.otp_message', [
            'otp' => $otp,
            'minutes' => $expiryMinutes
        ]);
        $sent = $this->manager->driver()->send($phone, $message);

        return [
            'success' => $sent,
            'message' => $sent ? Lang::get('otp::otp.otp_sent_successfully') : Lang::get('otp::otp.otp_send_failed'),
            'expires_in' => $expiryMinutes * 60,
            'remaining_time' => $remainingTime,
        ];
    }

    public function verify(string $phone, string $code): array
    {
        $otpData = Cache::get("otp:{$phone}");

        if (!$otpData) {
            return [
                'success' => false,
                'message' => Lang::get('otp::otp.otp_expired_or_not_found')
            ];
        }

        // Check attempts
        if ($otpData['attempts'] >= (int) Config::get('otp.max_attempts')) {
            $this->blockPhone($phone);
            Cache::forget("otp:{$phone}");
            return [
                'success' => false,
                'message' => Lang::get('otp::otp.max_attempts_exceeded')
            ];
        }

        // Verify code
        if ($otpData['code'] === $code) {
            Cache::forget("otp:{$phone}");
            Cache::forget("otp_last_sent:{$phone}");
            return [
                'success' => true,
                'message' => Lang::get('otp::otp.otp_verified_successfully')
            ];
        }

        // Increment attempts
        $otpData['attempts']++;
        Cache::put("otp:{$phone}", $otpData, Carbon::now()->addMinutes((int) Config::get('otp.otp_expiry')));

        $remainingAttempts = (int) Config::get('otp.max_attempts') - $otpData['attempts'];

        return [
            'success' => false,
            'message' => Lang::get('otp::otp.invalid_otp'),
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
        $elapsed = Carbon::parse($lastSent)->diffInSeconds(now());
        return (int) max(0, $resendDelay - $elapsed);
    }

    protected function isTestMode(string $phone): bool
    {
        // Check if global test mode is enabled
        if (Config::get('otp.test_mode', false)) {
            return true;
        }

        // Check if phone number is in test numbers list
        $testNumbers = Config::get('otp.test_numbers', []);
        return in_array($phone, $testNumbers);
    }

    protected function getTestOtp(): string
    {
        return Config::get('otp.test_otp', '8888');
    }
}
