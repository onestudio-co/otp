<?php

namespace OneStudio\Otp\Tests\Unit;

use OneStudio\Otp\Tests\TestCase;
use OneStudio\Otp\OtpService;
use OneStudio\Otp\OtpManager;
use OneStudio\Otp\Providers\TwilioProvider;
use Illuminate\Support\Facades\Cache;
use Mockery;

class OtpServiceTest extends TestCase
{
    protected $otpService;
    protected $mockManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the OtpManager
        $this->mockManager = Mockery::mock(OtpManager::class);
        $this->otpService = new OtpService($this->mockManager);
    }

    public function test_can_generate_otp()
    {
        // Mock the driver
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldReceive('send')->andReturn(true);
        $this->mockManager->shouldReceive('driver')->andReturn($mockDriver);

        // Clear any existing cache
        Cache::flush();

        $result = $this->otpService->generate('+201120305686');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertTrue($result['success']);
        $this->assertEquals(__('otp::otp.otp_sent_successfully'), $result['message']);
    }

    public function test_can_verify_otp()
    {
        // First generate an OTP
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldReceive('send')->andReturn(true);
        $this->mockManager->shouldReceive('driver')->andReturn($mockDriver);

        Cache::flush();
        $generateResult = $this->otpService->generate('+201120305686');
        $this->assertTrue($generateResult['success']);

        // Get the OTP from cache
        $otpData = Cache::get('otp:+201120305686');
        $this->assertNotNull($otpData);
        $this->assertArrayHasKey('code', $otpData);

        // Verify the OTP
        $verifyResult = $this->otpService->verify('+201120305686', $otpData['code']);

        $this->assertIsArray($verifyResult);
        $this->assertArrayHasKey('success', $verifyResult);
        $this->assertArrayHasKey('message', $verifyResult);
        $this->assertTrue($verifyResult['success']);
        $this->assertEquals(__('otp::otp.otp_verified_successfully'), $verifyResult['message']);
    }

    public function test_verification_fails_with_wrong_code()
    {
        // First generate an OTP
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldReceive('send')->andReturn(true);
        $this->mockManager->shouldReceive('driver')->andReturn($mockDriver);

        Cache::flush();
        $generateResult = $this->otpService->generate('+201120305686');
        $this->assertTrue($generateResult['success']);

        // Try to verify with wrong code
        $verifyResult = $this->otpService->verify('+201120305686', '9999');

        $this->assertIsArray($verifyResult);
        $this->assertArrayHasKey('success', $verifyResult);
        $this->assertArrayHasKey('message', $verifyResult);
        $this->assertArrayHasKey('remaining_attempts', $verifyResult);
        $this->assertFalse($verifyResult['success']);
        $this->assertEquals(__('otp::otp.invalid_otp'), $verifyResult['message']);
    }

    public function test_verification_fails_after_max_attempts()
    {
        // First generate an OTP
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldReceive('send')->andReturn(true);
        $this->mockManager->shouldReceive('driver')->andReturn($mockDriver);

        Cache::flush();
        $generateResult = $this->otpService->generate('+201120305686');
        $this->assertTrue($generateResult['success']);

        // Try to verify with wrong code multiple times (max attempts is 3)
        for ($i = 0; $i < 3; $i++) {
            $verifyResult = $this->otpService->verify('+201120305686', '9999');
            $this->assertFalse($verifyResult['success']);
        }

        // After max attempts, should be blocked
        $verifyResult = $this->otpService->verify('+201120305686', '9999');
        $this->assertFalse($verifyResult['success']);
        $this->assertEquals(__('otp::otp.max_attempts_exceeded'), $verifyResult['message']);
    }

    public function test_cannot_generate_otp_when_blocked()
    {
        // First generate and exhaust attempts to get blocked
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldReceive('send')->andReturn(true);
        $this->mockManager->shouldReceive('driver')->andReturn($mockDriver);

        Cache::flush();
        $generateResult = $this->otpService->generate('+201120305686');
        $this->assertTrue($generateResult['success']);

        // Exhaust attempts
        for ($i = 0; $i < 3; $i++) {
            $this->otpService->verify('+201120305686', '9999');
        }

        // Try to generate new OTP while blocked
        $generateResult = $this->otpService->generate('+201120305686');
        $this->assertFalse($generateResult['success']);
        // The message could be either blocked or resend delay message
        $this->assertContains($generateResult['message'], [
            __('otp::otp.too_many_attempts'),
            __('otp::otp.resend_delay_active', ['seconds' => 60])
        ]);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}
