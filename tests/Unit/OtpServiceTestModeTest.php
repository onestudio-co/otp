<?php

namespace OneStudio\Otp\Tests\Unit;

use OneStudio\Otp\Tests\TestCase;
use OneStudio\Otp\OtpService;
use OneStudio\Otp\OtpManager;
use OneStudio\Otp\Providers\TwilioProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;

class OtpServiceTestModeTest extends TestCase
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

    public function test_generate_otp_in_global_test_mode()
    {
        // Enable global test mode
        Config::set('otp.test_mode', true);
        Config::set('otp.test_otp', '1234');

        // Mock the driver (should not be called in test mode)
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldNotReceive('send');
        $this->mockManager->shouldNotReceive('driver');

        Cache::flush();

        $result = $this->otpService->generate('+201120305686');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(__('otp::otp.otp_sent_successfully'), $result['message']);
        $this->assertEquals('success', $result['type']);

        // Verify OTP is stored in cache
        $otpData = Cache::get('otp:+201120305686');
        $this->assertNotNull($otpData);
        $this->assertEquals('1234', $otpData['code']);
        $this->assertTrue($otpData['is_test']);
    }

    public function test_generate_otp_for_test_number()
    {
        // Disable global test mode but add phone to test numbers
        Config::set('otp.test_mode', false);
        Config::set('otp.test_otp', '5678');
        Config::set('otp.test_numbers', ['+201120305686', '+1234567890']);

        // Mock the driver (should not be called for test numbers)
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldNotReceive('send');
        $this->mockManager->shouldNotReceive('driver');

        Cache::flush();

        $result = $this->otpService->generate('+201120305686');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(__('otp::otp.otp_sent_successfully'), $result['message']);
        $this->assertEquals('success', $result['type']);

        // Verify OTP is stored in cache
        $otpData = Cache::get('otp:+201120305686');
        $this->assertNotNull($otpData);
        $this->assertEquals('5678', $otpData['code']);
        $this->assertTrue($otpData['is_test']);
    }

    public function test_generate_otp_for_non_test_number()
    {
        // Disable global test mode and phone is not in test numbers
        Config::set('otp.test_mode', false);
        Config::set('otp.test_numbers', ['+1234567890']);

        // Mock the driver (should be called for non-test numbers)
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldReceive('send')->andReturn(true);
        $this->mockManager->shouldReceive('driver')->andReturn($mockDriver);

        Cache::flush();

        $result = $this->otpService->generate('+201120305686');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(__('otp::otp.otp_sent_successfully'), $result['message']);
        $this->assertEquals('success', $result['type']);

        // Verify OTP is stored in cache
        $otpData = Cache::get('otp:+201120305686');
        $this->assertNotNull($otpData);
        $this->assertFalse($otpData['is_test']);
        $this->assertNotEquals('8888', $otpData['code']); // Should be random, not test OTP
    }

    public function test_verify_test_otp()
    {
        // Enable test mode
        Config::set('otp.test_mode', true);
        Config::set('otp.test_otp', '9999');

        // Mock the driver
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldNotReceive('send');
        $this->mockManager->shouldNotReceive('driver');

        Cache::flush();

        // Generate test OTP
        $generateResult = $this->otpService->generate('+201120305686');
        $this->assertTrue($generateResult['success']);
        $this->assertEquals('success', $generateResult['type']);

        // Verify test OTP
        $verifyResult = $this->otpService->verify('+201120305686', '9999');
        $this->assertTrue($verifyResult['success']);
        $this->assertEquals(__('otp::otp.otp_verified_successfully'), $verifyResult['message']);
    }

    public function test_verify_wrong_test_otp()
    {
        // Enable test mode
        Config::set('otp.test_mode', true);
        Config::set('otp.test_otp', '9999');

        // Mock the driver
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldNotReceive('send');
        $this->mockManager->shouldNotReceive('driver');

        Cache::flush();

        // Generate test OTP
        $generateResult = $this->otpService->generate('+201120305686');
        $this->assertTrue($generateResult['success']);

        // Try to verify with wrong OTP
        $verifyResult = $this->otpService->verify('+201120305686', '8888');
        $this->assertFalse($verifyResult['success']);
        $this->assertEquals(__('otp::otp.invalid_otp'), $verifyResult['message']);
    }

    public function test_test_mode_with_multiple_numbers()
    {
        // Set multiple test numbers
        Config::set('otp.test_mode', false);
        Config::set('otp.test_otp', '5555');
        Config::set('otp.test_numbers', ['+201120305686', '+1234567890', '+9876543210']);

        // Mock the driver
        $mockDriver = Mockery::mock(TwilioProvider::class);
        $mockDriver->shouldNotReceive('send');
        $this->mockManager->shouldNotReceive('driver');

        Cache::flush();

        // Test first number
        $result1 = $this->otpService->generate('+201120305686');
        $this->assertTrue($result1['success']);
        $this->assertEquals('success', $result1['type']);

        Cache::flush();

        // Test second number
        $result2 = $this->otpService->generate('+1234567890');
        $this->assertTrue($result2['success']);
        $this->assertEquals('success', $result2['type']);

        Cache::flush();

        // Test third number
        $result3 = $this->otpService->generate('+9876543210');
        $this->assertTrue($result3['success']);
        $this->assertEquals('success', $result3['type']);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}
