<?php

namespace OneStudio\Otp\Tests\Unit;

use OneStudio\Otp\Tests\TestCase;
use OneStudio\Otp\OtpService;
use OneStudio\Otp\OtpManager;
use OneStudio\Otp\Providers\TwilioProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;

class OtpServiceRateLimitTest extends TestCase
{
    protected $otpService;
    protected $mockManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the OtpManager
        $this->mockManager = Mockery::mock(OtpManager::class);
        $this->otpService = new OtpService($this->mockManager);

        // Enable rate limiting
        Config::set('otp.rate_limit.enabled', true);
        Config::set('otp.rate_limit.max_requests_per_hour', 3);
        Config::set('otp.rate_limit.block_duration', 60);
        // Disable resend delay for rate limiting tests
        Config::set('otp.resend_delay', 0);
    }

    public function test_phone_rate_limit_hourly_exceeded()
    {
        $phone = '+201120305686';

        // Mock the driver
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldReceive('send')->times(3)->andReturn(true);
        $this->mockManager->shouldReceive('driver')->times(3)->andReturn($mockDriver);

        Cache::flush();

        // Send 3 requests (should succeed)
        for ($i = 0; $i < 3; $i++) {
            $result = $this->otpService->generate($phone);
            $this->assertTrue($result['success']);
        }

        // 4th request should be rate limited and blocked
        $result = $this->otpService->generate($phone);
        $this->assertFalse($result['success']);
        $this->assertEquals('rate_limited', $result['type']);
        $this->assertStringContainsString('Rate limit exceeded', $result['message']);
        $this->assertStringContainsString('Blocked for', $result['message']);
        $this->assertArrayHasKey('remaining_time', $result);
        $this->assertEquals(3600, $result['remaining_time']); // 60 minutes in seconds
    }

    public function test_rate_limit_disabled()
    {
        // Disable rate limiting
        Config::set('otp.rate_limit.enabled', false);

        $phone = '+201120305686';

        // Mock the driver
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldReceive('send')->times(5)->andReturn(true);
        $this->mockManager->shouldReceive('driver')->times(5)->andReturn($mockDriver);

        Cache::flush();

        // Send multiple requests (should all succeed)
        for ($i = 0; $i < 5; $i++) {
            $result = $this->otpService->generate($phone);
            $this->assertTrue($result['success']);
            $this->assertEquals('success', $result['type']);
        }
    }

    public function test_rate_limit_reset_after_hour()
    {
        $phone = '+201120305686';

        // Mock the driver
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldReceive('send')->times(4)->andReturn(true);
        $this->mockManager->shouldReceive('driver')->times(4)->andReturn($mockDriver);

        Cache::flush();

        // Send 3 requests (should succeed)
        for ($i = 0; $i < 3; $i++) {
            $result = $this->otpService->generate($phone);
            $this->assertTrue($result['success']);
        }

        // 4th request should be rate limited and blocked
        $result = $this->otpService->generate($phone);
        $this->assertFalse($result['success']);
        $this->assertEquals('rate_limited', $result['type']);

        // Clear the block and old requests (simulate time passing)
        Cache::forget("otp_rate_blocked:{$phone}");
        Cache::forget("otp_requests:{$phone}");

        // Should work again
        $result = $this->otpService->generate($phone);
        $this->assertTrue($result['success']);
    }

    public function test_different_phones_different_limits()
    {
        $phone1 = '+201120305686';
        $phone2 = '+1234567890';

        // Mock the driver
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldReceive('send')->times(6)->andReturn(true);
        $this->mockManager->shouldReceive('driver')->times(6)->andReturn($mockDriver);

        Cache::flush();

        // Send 3 requests from phone1
        for ($i = 0; $i < 3; $i++) {
            $result = $this->otpService->generate($phone1);
            $this->assertTrue($result['success']);
        }

        // Send 3 requests from phone2 (should work - different phone)
        for ($i = 0; $i < 3; $i++) {
            $result = $this->otpService->generate($phone2);
            $this->assertTrue($result['success']);
        }

        // 4th request from phone1 should be rate limited
        $result = $this->otpService->generate($phone1);
        $this->assertFalse($result['success']);
        $this->assertEquals('rate_limited', $result['type']);
    }

    public function test_rolling_window_rate_limit()
    {
        $phone = '+201120305686';

        // Mock the driver
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldReceive('send')->times(3)->andReturn(true);
        $this->mockManager->shouldReceive('driver')->times(3)->andReturn($mockDriver);

        Cache::flush();

        // Send 3 requests (should succeed)
        for ($i = 0; $i < 3; $i++) {
            $result = $this->otpService->generate($phone);
            $this->assertTrue($result['success']);
        }

        // 4th request should be rate limited (no driver call)
        $result = $this->otpService->generate($phone);
        $this->assertFalse($result['success']);
        $this->assertEquals('rate_limited', $result['type']);

        // Clear the block
        Cache::forget("otp_rate_blocked:{$phone}");

        // Simulate time passing (but not a full hour)
        // This should still be rate limited because we're using rolling window
        $result = $this->otpService->generate($phone);
        $this->assertFalse($result['success']);
        $this->assertEquals('rate_limited', $result['type']);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}
