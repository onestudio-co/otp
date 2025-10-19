<?php

namespace OneStudio\Otp\Tests\Feature;

use OneStudio\Otp\Tests\TestCase;
use OneStudio\Otp\Facades\Otp;
use OneStudio\Otp\OtpService;
use OneStudio\Otp\OtpManager;
use OneStudio\Otp\Providers\TwilioProvider;
use Illuminate\Support\Facades\Cache;
use Mockery;

class OtpIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the Twilio provider to avoid actual SMS sending
        $mockProvider = Mockery::mock(TwilioProvider::class);
        $mockProvider->shouldReceive('send')->andReturn(true);
        
        // Mock the manager
        $mockManager = Mockery::mock(OtpManager::class);
        $mockManager->shouldReceive('driver')->andReturn($mockProvider);
        
        // Bind the mock manager
        $this->app->instance(OtpManager::class, $mockManager);
    }

    public function test_can_generate_otp_using_facade()
    {
        Cache::flush();

        $result = Otp::generate('+201120305686');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertTrue($result['success']);
    }

    public function test_can_verify_otp_using_facade()
    {
        Cache::flush();

        // Generate OTP
        $generateResult = Otp::generate('+201120305686');
        $this->assertTrue($generateResult['success']);

        // Get OTP from cache
        $otpData = Cache::get('otp:+201120305686');
        $this->assertNotNull($otpData);

        // Verify OTP
        $verifyResult = Otp::verify('+201120305686', $otpData['code']);
        $this->assertTrue($verifyResult['success']);
    }

    public function test_full_otp_flow()
    {
        Cache::flush();

        $phone = '+201120305686';

        // Step 1: Generate OTP
        $generateResult = Otp::generate($phone);
        $this->assertTrue($generateResult['success']);
        $this->assertEquals('OTP sent successfully.', $generateResult['message']);

        // Step 2: Verify OTP
        $otpData = Cache::get("otp:{$phone}");
        $this->assertNotNull($otpData);
        $this->assertArrayHasKey('code', $otpData);
        $this->assertArrayHasKey('attempts', $otpData);

        $verifyResult = Otp::verify($phone, $otpData['code']);
        $this->assertTrue($verifyResult['success']);
        $this->assertEquals('OTP verified successfully.', $verifyResult['message']);

        // Step 3: OTP should be removed after successful verification
        $this->assertNull(Cache::get("otp:{$phone}"));
    }

    public function test_resend_delay_enforcement()
    {
        Cache::flush();

        $phone = '+201120305686';

        // Generate first OTP
        $result1 = Otp::generate($phone);
        $this->assertTrue($result1['success']);

        // Try to generate immediately (should fail due to resend delay)
        $result2 = Otp::generate($phone);
        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('Please wait', $result2['message']);
        $this->assertArrayHasKey('remaining_time', $result2);
    }

    public function test_otp_expiry()
    {
        Cache::flush();

        $phone = '+201120305686';

        // Generate OTP
        $generateResult = Otp::generate($phone);
        $this->assertTrue($generateResult['success']);

        // Get OTP data
        $otpData = Cache::get("otp:{$phone}");
        $this->assertNotNull($otpData);

        // Simulate expiry by removing from cache
        Cache::forget("otp:{$phone}");

        // Try to verify expired OTP
        $verifyResult = Otp::verify($phone, $otpData['code']);
        $this->assertFalse($verifyResult['success']);
        $this->assertEquals('OTP expired or not found.', $verifyResult['message']);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}
