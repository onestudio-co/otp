<?php

declare(strict_types=1);

namespace OneStudio\Otp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Carbon;
use OneStudio\Otp\OtpManager;
use OneStudio\Otp\Enums\OtpResponseType;

class OtpService
{
    protected $manager;

    public function __construct(OtpManager $manager)
    {
        $this->manager = $manager;
    }

    public function generate(string $phone): array
    {
        // Check if rate limiting is enabled
        if (Config::get('otp.rate_limit.enabled', true)) {
            $rateLimitCheck = $this->checkRateLimit($phone);
            if (!$rateLimitCheck['allowed']) {
                return [
                    'success' => false,
                    'message' => $rateLimitCheck['message'],
                    'remaining_time' => $rateLimitCheck['remaining_time'],
                    'type' => $rateLimitCheck['type']->value
                ];
            }
        }

        // Check if blocked
        if ($this->isBlocked($phone)) {
            $blockedUntil = Cache::get("otp_blocked:{$phone}");
            $remainingTime = (int) Carbon::now()->diffInSeconds(Carbon::parse($blockedUntil));
            return [
                'success' => false,
                'message' => Lang::get('otp::otp.too_many_attempts'),
                'remaining_time' => $remainingTime,
                'type' => OtpResponseType::BLOCKED->value
            ];
        }
        // Check resend delay
        if (!$this->canResend($phone)) {
            $remainingTime = $this->getRemainingResendTime($phone);
            return [
                'success' => false,
                'message' => Lang::get('otp::otp.resend_delay_active', ['seconds' => $remainingTime]),
                'remaining_time' => $remainingTime,
                'type' => OtpResponseType::RESEND_DELAY->value
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
            // Record the request for rate limiting even in test mode
            if (Config::get('otp.rate_limit.enabled', true)) {
                $this->recordRequest($phone);
            }
            
            return [
                'success' => true,
                'message' => Lang::get('otp::otp.otp_sent_successfully'),
                'remaining_time' => Config::get('otp.resend_delay'),
                'type' => OtpResponseType::SUCCESS->value
            ];
        }

        // Send actual OTP
        $message = Lang::get('otp::otp.otp_message', [
            'otp' => $otp,
            'minutes' => $expiryMinutes
        ]);
        $sent = $this->manager->driver()->send($phone, $otp, $message);

        // Record the request for rate limiting
        if (Config::get('otp.rate_limit.enabled', true)) {
            $this->recordRequest($phone);
        }

        return [
            'success' => $sent,
            'message' => $sent ? Lang::get('otp::otp.otp_sent_successfully') : Lang::get('otp::otp.otp_send_failed'),
            'remaining_time' => $sent ? Config::get('otp.resend_delay') : null,
            'type' => $sent ? OtpResponseType::SUCCESS->value : OtpResponseType::SEND_FAILED->value
        ];
    }

    public function verify(string $phone, string $code): array
    {
        $otpData = Cache::get("otp:{$phone}");
        $isBlocked = $this->isBlocked($phone);

        // Check attempts
        if (isset($otpData['attempts']) && $otpData['attempts'] >= (int) Config::get('otp.max_attempts') || $isBlocked) {
            if (!$isBlocked) {
                $this->blockPhone($phone);
                Cache::forget("otp:{$phone}");
            }
            $blockedUntil = Cache::get("otp_blocked:{$phone}");
            $remainingTime = (int) Carbon::now()->diffInSeconds(Carbon::parse($blockedUntil));
            return [
                'success' => false,
                'message' => Lang::get('otp::otp.max_attempts_exceeded', ['minutes' => ceil($remainingTime / 60)]),
                'remaining_time' => $remainingTime,
                'type' => OtpResponseType::BLOCKED->value
            ];
        }

        if (!$otpData) {
            return [
                'success' => false,
                'message' => Lang::get('otp::otp.otp_expired_or_not_found')
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

    protected function checkRateLimit(string $phone): array
    {
        $now = Carbon::now();
        
        // Check if phone is already blocked due to rate limiting
        $rateLimitBlockKey = "otp_rate_blocked:{$phone}";
        if (Cache::has($rateLimitBlockKey)) {
            $blockedUntil = Cache::get($rateLimitBlockKey);
            $remainingTime = (int) now()->diffInSeconds(Carbon::parse($blockedUntil));
            
            return [
                'allowed' => false,
                'message' => Lang::get('otp::otp.rate_limit_blocked', ['minutes' => ceil($remainingTime / 60)]),
                'remaining_time' => $remainingTime,
                'type' => OtpResponseType::RATE_LIMITED
            ];
        }
        
        // Check requests in the last hour using a rolling window
        $requests = $this->getRequestsInLastHour($phone);
        $maxRequests = Config::get('otp.rate_limit.max_requests_per_hour', 3);
        
        if (count($requests) >= $maxRequests) {
            // Block the phone for the configured duration
            $blockDuration = Config::get('otp.rate_limit.block_duration', 60);
            $blockedUntil = $now->addMinutes($blockDuration);
            Cache::put($rateLimitBlockKey, $blockedUntil, $blockedUntil);
            
            return [
                'allowed' => false,
                'message' => Lang::get('otp::otp.rate_limit_exceeded', ['limit' => $maxRequests, 'minutes' => $blockDuration]),
                'remaining_time' => $blockDuration * 60, // Convert to seconds
                'type' => OtpResponseType::RATE_LIMITED
            ];
        }
        
        return ['allowed' => true];
    }

    protected function getRequestsInLastHour(string $phone): array
    {
        $now = Carbon::now();
        $oneHourAgo = $now->copy()->subHour();
        
        // Get all request timestamps for this phone
        $requestsKey = "otp_requests:{$phone}";
        $requests = Cache::get($requestsKey, []);
        
        // Filter requests from the last hour
        $recentRequests = array_filter($requests, function($timestamp) use ($oneHourAgo) {
            return Carbon::parse($timestamp)->gte($oneHourAgo);
        });
        
        return $recentRequests;
    }

    protected function recordRequest(string $phone): void
    {
        $now = Carbon::now();
        
        // Get existing requests
        $requestsKey = "otp_requests:{$phone}";
        $requests = Cache::get($requestsKey, []);
        
        // Add current request timestamp
        $requests[] = $now->toISOString();
        
        // Keep only requests from the last 24 hours (cleanup old data)
        $oneDayAgo = $now->copy()->subDay();
        $requests = array_filter($requests, function($timestamp) use ($oneDayAgo) {
            return Carbon::parse($timestamp)->gte($oneDayAgo);
        });
        
        // Store updated requests (expire after 24 hours)
        Cache::put($requestsKey, $requests, $now->copy()->addDay());
    }
}
